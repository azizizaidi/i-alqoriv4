<?php
namespace App\Http\Controllers;

use App\Models\ReportClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function createBill(ReportClass $pay)
    {
        $report = $pay;

        $some_data = array(
            'userSecretKey'=> config('toyyibpay.key'),
            'categoryCode'=> config('toyyibpay.category'),
            'billName'=>$report->registrar->code,
            'billDescription'=>$report->month,
            'billPriceSetting'=>1,
            'billPayorInfo'=>1,
            'billAmount'=>$report->fee_student * 100,
            'billReturnUrl'=> route('toyyibpay.paymentstatus', $report->id),
            'billCallbackUrl'=> route('toyyibpay.callback'),
            'billExternalReferenceNo' => $report->id, // ✅ Ini yang penting untuk sync
            'billTo'=>$report->registrar->name,
            'billEmail'=>'resityuranalqori@gmail.com',
            'billPhone'=>'0183879635',
            'billSplitPayment'=>0,
            'billSplitPaymentArgs'=>'',
            'billPaymentChannel'=>0,
            'billContentEmail'=>'Terima kasih kerana telah bayar yuran mengaji! :)',
            'billChargeToCustomer'=>1,
        );

        $url = 'https://toyyibpay.com/index.php/api/createBill';
        $response = Http::asForm()->post($url, $some_data);

        if ($response->successful()) {
            $responseData = $response->json();
            $billCode = $responseData[0]['BillCode'];

            // ✅ Optional: Simpan bill_code jika nak, tapi tak wajib untuk sync
            // $report->bill_code = $billCode;
            // $report->save();

            session([
                'billAmount' => $report->fee_student,
                'billCode' => $billCode
            ]);

           Notification::make()
                ->title('Bil Yuran Berjaya Dibuat')
                ->success()
                ->body('Bil Yuran Anda Berjaya Dibuat!')
                ->send();

            return redirect('https://toyyibpay.com/' . $billCode);
        } else {
            Log::error('ToyyibPay createBill failed', [
                'report_id' => $report->id,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            Notification::make()
                ->title('Bil Yuran Gagal Dibuat')
                ->danger()
                ->body('Sila rujuk encik Nazirul.')
                ->send();

            return redirect()->route('filament.admin.pages.monthly-fee');
        }
    }

    public function paymentStatus(Request $request, $id)
    {
        $status_id = $request->input('status_id');
        $billcode = $request->input('billcode');
        $order_id = $request->input('order_id');
        $msg = $request->input('msg');
        $transaction_id = $request->input('transaction_id');

        Log::info('Payment Status Callback', [
            'id' => $id,
            'status_id' => $status_id,
            'billcode' => $billcode,
            'order_id' => $order_id,
            'transaction_id' => $transaction_id
        ]);

        if ($status_id == 1) {
            $item = ReportClass::find($id);
            if ($item) {
                $item->status = 1;
                $item->transaction_time = now();
                $item->save();

                session()->forget(['billAmount', 'billCode']);

                Notification::make()
                    ->title('Pembayaran Telah Berjaya')
                    ->success()
                    ->body('Terima kasih telah membuat pembayaran yuran!')
                    ->seconds(10)
                    ->send();

                return redirect()->route('filament.admin.pages.monthly-fee');
            } else {
                Log::error('Payment Status: Report not found', ['id' => $id]);
                
                Notification::make()
                    ->title('Yuran ID Tidak Dijumpai')
                    ->danger()
                    ->body('Sila hubungi encik Nazirul.')
                    ->seconds(10)
                    ->send();

                return redirect()->route('filament.admin.pages.monthly-fee');
            }
        } else {
            Log::warning('Payment failed', [
                'id' => $id,
                'status_id' => $status_id,
                'msg' => $msg
            ]);

            Notification::make()
                ->title('Pembayaran Telah Gagal')
                ->danger()
                ->body('Anda gagal membuat pembayaran yuran. Sila cuba lagi.')
                ->send();

            return redirect()->route('filament.admin.pages.monthly-fee');
        }
    }

    public function callback(Request $request)
    {
        $response = $request->all(['refno', 'status', 'reason', 'billcode', 'order_id', 'amount']);
        Log::info('Toyyibpay Callback:', $response);
        
        // ✅ Guna refno (billExternalReferenceNo) untuk cari rekod
        if (isset($response['status']) && $response['status'] == 1 && isset($response['refno'])) {
            $item = ReportClass::find($response['refno']);
            
            if ($item && $item->status != 1) {
                $item->status = 1;
                $item->transaction_time = now();
                $item->save();
                
                Log::info('Payment status updated via callback', [
                    'report_id' => $response['refno'],
                    'billcode' => $response['billcode']
                ]);
            }
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * ✅ Sync menggunakan getAllBillTransactions API (semua transactions)
     * Kemudian match dengan billExternalReferenceNo
     */
    public function syncAllUnpaidBills()
    {
        Log::info('Starting sync using getAllBillTransactions');

        try {
            // Get all transactions from ToyyibPay
            $response = Http::timeout(60)->asForm()->post('https://toyyibpay.com/index.php/api/getAllBillTransactions', [
                'userSecretKey' => config('toyyibpay.key'),
                'categoryCode' => config('toyyibpay.category'),
            ]);

            if (!$response->successful()) {
                Log::error('ToyyibPay getAllBillTransactions failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'total_checked' => 0,
                    'updated' => 0,
                    'errors' => 1,
                    'error_details' => ['API call failed']
                ];
            }

            $allTransactions = $response->json();
            
            if (!is_array($allTransactions)) {
                Log::warning('Invalid response format from ToyyibPay');
                return [
                    'total_checked' => 0,
                    'updated' => 0,
                    'errors' => 1,
                    'error_details' => ['Invalid response format']
                ];
            }

            $updatedCount = 0;
            $checkedCount = 0;
            $errors = [];

            // Filter untuk transactions yang paid sahaja
            foreach ($allTransactions as $transaction) {
                if (!is_array($transaction)) {
                    continue;
                }

                $checkedCount++;

                // Check jika transaction berjaya dan ada external reference
                if (isset($transaction['billpaymentStatus']) && 
                    $transaction['billpaymentStatus'] == '1' && 
                    isset($transaction['billExternalReferenceNo'])) {
                    
                    $externalRefNo = $transaction['billExternalReferenceNo'];
                    
                    // Cari rekod dalam sistem menggunakan ID
                    $report = ReportClass::find($externalRefNo);
                    
                    if ($report && $report->status != 1) {
                        try {
                            $report->status = 1;
                            
                            // Set transaction time dari ToyyibPay
                            if (isset($transaction['billpaymentDate']) && !empty($transaction['billpaymentDate'])) {
                                $report->transaction_time = Carbon::parse($transaction['billpaymentDate']);
                            } else {
                                $report->transaction_time = now();
                            }
                            
                            $report->save();
                            $updatedCount++;
                            
                            Log::info('Payment synced successfully', [
                                'report_id' => $report->id,
                                'external_ref' => $externalRefNo,
                                'payment_date' => $report->transaction_time
                            ]);
                            
                        } catch (\Exception $e) {
                            $errors[] = "Failed to update report ID {$externalRefNo}: " . $e->getMessage();
                            Log::error('Failed to update report', [
                                'report_id' => $externalRefNo,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }

            $result = [
                'total_checked' => $checkedCount,
                'updated' => $updatedCount,
                'errors' => count($errors),
                'error_details' => $errors
            ];

            Log::info('Sync completed', $result);
            return $result;

        } catch (\Exception $e) {
            Log::error('Sync process failed', ['error' => $e->getMessage()]);
            
            return [
                'total_checked' => 0,
                'updated' => 0,
                'errors' => 1,
                'error_details' => [$e->getMessage()]
            ];
        }
    }

    /**
     * ✅ Alternative: Sync specific unpaid bills dari sistem
     */
    public function syncUnpaidBillsFromSystem()
    {
        Log::info('Starting sync from system records');

        // Get unpaid bills from system
        $unpaidBills = ReportClass::where('status', 0)->get();
        
        if ($unpaidBills->isEmpty()) {
            return [
                'total_checked' => 0,
                'updated' => 0,
                'errors' => 0,
                'error_details' => []
            ];
        }

        // Get all paid transactions from ToyyibPay
        try {
            $response = Http::timeout(60)->asForm()->post('https://toyyibpay.com/index.php/api/getAllBillTransactions', [
                'userSecretKey' => config('toyyibpay.key'),
                'categoryCode' => config('toyyibpay.category'),
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch transactions from ToyyibPay');
            }

            $allTransactions = $response->json();
            
            // Create lookup array untuk transactions yang paid
            $paidTransactions = [];
            foreach ($allTransactions as $transaction) {
                if (isset($transaction['billpaymentStatus']) && 
                    $transaction['billpaymentStatus'] == '1' &&
                    isset($transaction['billExternalReferenceNo'])) {
                    
                    $paidTransactions[$transaction['billExternalReferenceNo']] = $transaction;
                }
            }

            $updatedCount = 0;
            $errors = [];

            // Check setiap unpaid bill
            foreach ($unpaidBills as $bill) {
                if (isset($paidTransactions[$bill->id])) {
                    try {
                        $transaction = $paidTransactions[$bill->id];
                        
                        $bill->status = 1;
                        if (isset($transaction['billpaymentDate']) && !empty($transaction['billpaymentDate'])) {
                            $bill->transaction_time = Carbon::parse($transaction['billpaymentDate']);
                        } else {
                            $bill->transaction_time = now();
                        }
                        
                        $bill->save();
                        $updatedCount++;
                        
                        Log::info('Bill updated from system sync', [
                            'bill_id' => $bill->id,
                            'payment_date' => $bill->transaction_time
                        ]);
                        
                    } catch (\Exception $e) {
                        $errors[] = "Failed to update bill ID {$bill->id}: " . $e->getMessage();
                    }
                }
            }

            return [
                'total_checked' => $unpaidBills->count(),
                'updated' => $updatedCount,
                'errors' => count($errors),
                'error_details' => $errors
            ];

        } catch (\Exception $e) {
            Log::error('System sync failed', ['error' => $e->getMessage()]);
            
            return [
                'total_checked' => $unpaidBills->count(),
                'updated' => 0,
                'errors' => 1,
                'error_details' => [$e->getMessage()]
            ];
        }
    }

    /**
     * ✅ Get payment details for specific report ID
     */
    public function getPaymentDetails($reportId)
    {
        try {
            $response = Http::timeout(30)->asForm()->post('https://toyyibpay.com/index.php/api/getAllBillTransactions', [
                'userSecretKey' => config('toyyibpay.key'),
                'categoryCode' => config('toyyibpay.category'),
            ]);

            if ($response->successful()) {
                $transactions = $response->json();
                
                foreach ($transactions as $transaction) {
                    if (isset($transaction['billExternalReferenceNo']) && 
                        $transaction['billExternalReferenceNo'] == $reportId) {
                        return [
                            'found' => true,
                            'transaction' => $transaction
                        ];
                    }
                }
                
                return ['found' => false];
            }
            
            return ['found' => false, 'error' => 'API call failed'];
            
        } catch (\Exception $e) {
            return ['found' => false, 'error' => $e->getMessage()];
        }
    }
}