<?php
namespace App\Http\Controllers;

use App\Models\ReportClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class PaymentController extends Controller
{
    public function createBill(ReportClass $pay)
    {
        $report = request('pay', 'id');

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
            'billExternalReferenceNo' => $report->id,
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
            $billCode = $response[0]['BillCode'];

            // Save the billCode to the report
            $report->bill_code = $billCode;
            $report->save();

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
        $status_id = request()->input('status_id');
        $billcode = request()->input('billcode');
        $order_id = request()->input('order_id');
        $msg = request()->input('msg');
        $transaction_id = request()->input('transaction_id');

        if ($status_id == 1) {
            $item = ReportClass::find($id);
            if ($item) {
                $item->status = 1;
                $item->transaction_time = now();
                $item->save();

                $billAmount = session('billAmount');
                $billCode = session('billCode');

                Notification::make()
                    ->title('Pembayaran Telah Berjaya')
                    ->success()
                    ->body('Terima kasih telah membuat pembayaran yuran!')
                    ->seconds(10)
                    ->send();

                return redirect()->route('filament.admin.pages.monthly-fee');
            } else {
                Notification::make()
                    ->title('Yuran ID Tidak Dijumpai')
                    ->danger()
                    ->body('Sila hubungi encik Nazirul.')
                    ->seconds(10)
                    ->send();

                return redirect()->route('filament.admin.pages.monthly-fee');
            }
        } else {
            Notification::make()
                ->title('Pembayaran Telah Gagal')
                ->danger()
                ->body('Anda gagal membuat pembayaran yuran. Sila cuba lagi.')
                ->send();

            return redirect()->route('filament.admin.pages.monthly-fee');
        }
    }

    public function callback()
    {
        $response = request()->all(['refno', 'status', 'reason', 'billcode', 'order_id', 'amount']);
        Log::info('Toyyibpay Callback:', $response);
        
        // Process callback data to update payment status
        if (isset($response['status']) && $response['status'] == 1) {
            // Find the record by bill code or external reference
            $item = ReportClass::where('bill_code', $response['billcode'])->first();
            
            if ($item && $item->status != 1) {
                $item->status = 1;
                $item->transaction_time = now();
                $item->save();
                
                Log::info('Payment status updated via callback for bill: ' . $response['billcode']);
            }
        }
    }

    public function billTransaction($billCode)
    {
        Log::info('Checking bill transaction for: ' . $billCode);
        
        $response = Http::asForm()->post('https://toyyibpay.com/index.php/api/getBillTransactions', [
            'userSecretKey' => config('toyyibpay.key'),
            'billCode' => $billCode,
        ]);

        if ($response->successful()) {
            $transactions = json_decode($response->body(), true);

            Log::info('Bill Transactions Response for ' . $billCode . ':', ['raw' => $transactions]);

            // Handle different response formats
            if (is_array($transactions) && !empty($transactions)) {
                foreach ($transactions as $trx) {
Log::info('Processing transaction:', $trx);
// Check if transaction is successful (status = 1 means paid)
if (isset($trx['billpaymentStatus']) && $trx['billpaymentStatus'] == '1') {
    // Find the record using external reference number or bill code
    $externalRef = $trx['billExternalReferenceNo'] ?? null;
    $item = null;
    
    if ($externalRef) {
        $item = ReportClass::find($externalRef);
    }
    
    // If not found by external ref, try by bill code
    if (!$item) {
        $item = ReportClass::where('bill_code', $billCode)->first();
    }

    if ($item && $item->status != 1) {
        $item->status = 1;
        $item->transaction_time = isset($trx['billpaymentDate']) ? 
            \Carbon\Carbon::parse($trx['billpaymentDate']) : now();
        $item->save();
        
        Log::info('Updated payment status for record ID: ' . $item->id);
    }
}
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Transaksi telah disemak dan dikemaskini.',
                ]);
            } else {
                Log::warning('No transactions found or invalid format for bill: ' . $billCode, ['transactions' => $transactions]);
            }
        } else {
            Log::error('Toyyibpay API error for bill: ' . $billCode, [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Gagal semak transaksi dari ToyyibPay',
        ], 500);
    }

    /**
     * Sync all unpaid bills with Toyyibpay
     */
    public function syncAllUnpaidBills()
    {
        $unpaidBills = ReportClass::where('status', 0)
            ->whereNotNull('bill_code')
            ->get();

        $updatedCount = 0;
        $errorCount = 0;

        foreach ($unpaidBills as $bill) {
            try {
                $result = $this->syncSingleBill($bill);
                if ($result) {
                    $updatedCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Error syncing bill ID: ' . $bill->id, ['error' => $e->getMessage()]);
            }
        }

        Log::info('Sync completed', [
            'total_checked' => $unpaidBills->count(),
            'updated' => $updatedCount,
            'errors' => $errorCount
        ]);

        return [
            'total_checked' => $unpaidBills->count(),
            'updated' => $updatedCount,
            'errors' => $errorCount
        ];
    }

    /**
     * Sync a single bill with Toyyibpay
     */
    private function syncSingleBill(ReportClass $bill)
    {
        if (!$bill->bill_code) {
            return false;
        }

        $response = Http::asForm()->post('https://toyyibpay.com/index.php/api/getBillTransactions', [
            'userSecretKey' => config('toyyibpay.key'),
            'billCode' => $bill->bill_code,
        ]);

        if ($response->successful()) {
            $transactions = json_decode($response->body(), true);

            if (is_array($transactions) && !empty($transactions)) {
                foreach ($transactions as $trx) {
                    // Check if payment is successful
                    if (isset($trx['billpaymentStatus']) && $trx['billpaymentStatus'] == '1') {
                        $bill->status = 1;
                        $bill->transaction_time = isset($trx['billpaymentDate']) ? 
                            \Carbon\Carbon::parse($trx['billpaymentDate']) : now();
                        $bill->save();
                        
                        return true;
                    }
                }
            }
        }

        return false;
    }
}