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
    // Log semua data yang diterima untuk debugging
    $allData = $request->all();
    Log::info('Toyyibpay Callback - All Data:', $allData);
    
    $response = $request->all(['refno', 'status', 'reason', 'billcode', 'order_id', 'amount']);
    Log::info('Toyyibpay Callback - Filtered Data:', $response);
    
    try {
        // Validate required fields
        if (!isset($response['refno']) || empty($response['refno'])) {
            Log::error('Callback missing refno', $response);
            return response()->json(['status' => 'error', 'message' => 'Missing refno']);
        }
        
        if (!isset($response['status'])) {
            Log::error('Callback missing status', $response);
            return response()->json(['status' => 'error', 'message' => 'Missing status']);
        }
        
        // Only process successful payments
        if ($response['status'] == 1) {
            $reportId = $response['refno'];
            $item = ReportClass::find($reportId);
            
            if (!$item) {
                Log::error('Report not found in callback', [
                    'report_id' => $reportId,
                    'callback_data' => $response
                ]);
                return response()->json(['status' => 'error', 'message' => 'Report not found']);
            }
            
            // Only update if not already paid
            if ($item->status != 1) {
                $item->status = 1;
                $item->transaction_time = now();
                
                // Optional: Store transaction details
                if (isset($response['billcode'])) {
                    $item->bill_code = $response['billcode'];
                }
                if (isset($response['order_id'])) {
                    $item->toyyibpay_order_id = $response['order_id'];
                }
                
                $item->save();
                
                Log::info('Payment status updated via callback', [
                    'report_id' => $reportId,
                    'billcode' => $response['billcode'] ?? 'N/A',
                    'order_id' => $response['order_id'] ?? 'N/A',
                    'amount' => $response['amount'] ?? 'N/A'
                ]);
                
                return response()->json(['status' => 'success', 'message' => 'Payment updated']);
            } else {
                Log::info('Payment already processed', [
                    'report_id' => $reportId,
                    'current_status' => $item->status
                ]);
                return response()->json(['status' => 'success', 'message' => 'Already processed']);
            }
        } else {
            // Payment failed
            Log::warning('Payment failed in callback', [
                'report_id' => $response['refno'] ?? 'N/A',
                'status' => $response['status'],
                'reason' => $response['reason'] ?? 'N/A'
            ]);
            return response()->json(['status' => 'received', 'message' => 'Payment failed']);
        }
        
    } catch (\Exception $e) {
        Log::error('Callback processing failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'callback_data' => $response
        ]);
        
        return response()->json(['status' => 'error', 'message' => 'Processing failed']);
    }
}

// Method untuk manual check dan fix payments
public function manualSyncCheck()
{
    Log::info('Starting manual sync check');
    
    // Check recent unpaid bills (last 30 days)
    $recentUnpaid = ReportClass::where('status', 0)
        ->where('created_at', '>=', now()->subDays(30))
        ->get();
    
    if ($recentUnpaid->isEmpty()) {
        return [
            'message' => 'No recent unpaid bills found',
            'total_checked' => 0,
            'updated' => 0
        ];
    }
    
    $results = $this->syncSpecificReports($recentUnpaid->pluck('id')->toArray());
    
    Log::info('Manual sync check completed', $results);
    return $results;
}

// Cron job method untuk daily sync
public function dailySync()
{
    Log::info('Starting daily sync process');
    
    try {
        // Sync from system (more efficient for daily runs)
        $systemSyncResults = $this->syncUnpaidBillsFromSystem();
        
        // Log results
        Log::info('Daily sync completed', $systemSyncResults);
        
        // Send notification if there are errors
        if ($systemSyncResults['errors'] > 0) {
            // You can add email notification here
            Log::error('Daily sync had errors', $systemSyncResults['error_details']);
        }
        
        return $systemSyncResults;
        
    } catch (\Exception $e) {
        Log::error('Daily sync failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return [
            'total_checked' => 0,
            'updated' => 0,
            'errors' => 1,
            'error_details' => [$e->getMessage()]
        ];
    }
}
    /**
     * ✅ Sync menggunakan getAllBillTransactions API (semua transactions)
     * Kemudian match dengan billExternalReferenceNo
     */
    public function syncAllUnpaidBills()
    {
        Log::info('Starting enhanced sync using getAllBillTransactions');

        try {
            // Get all transactions from ToyyibPay with extended timeout
            $response = Http::timeout(120)->asForm()->post('https://toyyibpay.com/index.php/api/getAllBillTransactions', [
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
                    'error_details' => ['API call failed: ' . $response->status()]
                ];
            }

            $allTransactions = $response->json();
            
            if (!is_array($allTransactions)) {
                Log::warning('Invalid response format from ToyyibPay', ['response' => $allTransactions]);
                return [
                    'total_checked' => 0,
                    'updated' => 0,
                    'errors' => 1,
                    'error_details' => ['Invalid response format from ToyyibPay']
                ];
            }

            $updatedCount = 0;
            $checkedCount = 0;
            $skippedCount = 0;
            $errors = [];

            Log::info('Processing transactions', ['total_transactions' => count($allTransactions)]);

            // Process semua transactions
            foreach ($allTransactions as $transaction) {
                if (!is_array($transaction)) {
                    $skippedCount++;
                    continue;
                }

                $checkedCount++;

                // Log untuk debugging
                if ($checkedCount <= 5) {
                    Log::debug('Sample transaction structure', $transaction);
                }

                // Check jika transaction berjaya dan ada external reference
                if (isset($transaction['billpaymentStatus']) && 
                    $transaction['billpaymentStatus'] == '1' && 
                    isset($transaction['billExternalReferenceNo']) &&
                    !empty($transaction['billExternalReferenceNo'])) {
                    
                    $externalRefNo = $transaction['billExternalReferenceNo'];
                    
                    // Cari rekod dalam sistem menggunakan ID
                    $report = ReportClass::find($externalRefNo);
                    
                    if ($report) {
                        // Update hanya jika status masih unpaid
                        if ($report->status != 1) {
                            try {
                                $report->status = 1;
                                
                                // Set transaction time dari ToyyibPay dengan multiple date formats
                                $transactionTime = $this->parseTransactionDate($transaction);
                                $report->transaction_time = $transactionTime;
                                
                                $report->save();
                                $updatedCount++;
                                
                                Log::info('Payment synced successfully', [
                                    'report_id' => $report->id,
                                    'external_ref' => $externalRefNo,
                                    'payment_date' => $report->transaction_time,
                                    'billcode' => $transaction['billCode'] ?? 'N/A'
                                ]);
                                
                            } catch (\Exception $e) {
                                $errors[] = "Failed to update report ID {$externalRefNo}: " . $e->getMessage();
                                Log::error('Failed to update report', [
                                    'report_id' => $externalRefNo,
                                    'error' => $e->getMessage(),
                                    'transaction' => $transaction
                                ]);
                            }
                        } else {
                            Log::debug('Report already paid', ['report_id' => $externalRefNo]);
                        }
                    } else {
                        Log::warning('Report not found in system', [
                            'external_ref' => $externalRefNo,
                            'billcode' => $transaction['billCode'] ?? 'N/A'
                        ]);
                    }
                }
            }

            $result = [
                'total_checked' => $checkedCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'errors' => count($errors),
                'error_details' => $errors
            ];

            Log::info('Enhanced sync completed', $result);
            return $result;

        } catch (\Exception $e) {
            Log::error('Sync process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'total_checked' => 0,
                'updated' => 0,
                'errors' => 1,
                'error_details' => [$e->getMessage()]
            ];
        }
    }

    /**
     * ✅ Parse transaction date dari berbagai format yang mungkin
     */
    private function parseTransactionDate($transaction)
    {
        $dateFields = ['billpaymentDate', 'billPaymentDate', 'paymentDate', 'transactionDate', 'dateTime'];
        
        foreach ($dateFields as $field) {
            if (isset($transaction[$field]) && !empty($transaction[$field])) {
                try {
                    // Try different date formats
                    $dateFormats = [
                        'Y-m-d H:i:s',
                        'd/m/Y H:i:s',
                        'Y-m-d',
                        'd/m/Y',
                        'Y-m-d\TH:i:s',
                        'Y-m-d\TH:i:s\Z'
                    ];
                    
                    foreach ($dateFormats as $format) {
                        try {
                            return Carbon::createFromFormat($format, $transaction[$field]);
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                    
                    // If custom formats fail, try Carbon's flexible parsing
                    return Carbon::parse($transaction[$field]);
                    
                } catch (\Exception $e) {
                    Log::warning('Failed to parse date', [
                        'field' => $field,
                        'value' => $transaction[$field],
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
        }
        
        // Fallback to current time if no valid date found
        Log::warning('No valid transaction date found, using current time', [
            'transaction_fields' => array_keys($transaction)
        ]);
        return now();
    }

    /**
     * ✅ Enhanced sync dari sistem dengan better performance
     */
    public function syncUnpaidBillsFromSystem()
    {
        Log::info('Starting enhanced sync from system records');

        // Get unpaid bills from system (limit untuk performance)
        $unpaidBills = ReportClass::where('status', 0)
            ->orderBy('created_at', 'desc')
            ->limit(1000) // Limit untuk avoid timeout
            ->get();
        
        if ($unpaidBills->isEmpty()) {
            Log::info('No unpaid bills found in system');
            return [
                'total_checked' => 0,
                'updated' => 0,
                'errors' => 0,
                'error_details' => []
            ];
        }

        Log::info('Found unpaid bills', ['count' => $unpaidBills->count()]);

        // Get all paid transactions from ToyyibPay
        try {
            $response = Http::timeout(120)->asForm()->post('https://toyyibpay.com/index.php/api/getAllBillTransactions', [
                'userSecretKey' => config('toyyibpay.key'),
                'categoryCode' => config('toyyibpay.category'),
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch transactions from ToyyibPay: ' . $response->status());
            }

            $allTransactions = $response->json();
            
            if (!is_array($allTransactions)) {
                throw new \Exception('Invalid response format from ToyyibPay');
            }
            
            // Create lookup array untuk transactions yang paid (more efficient)
            $paidTransactions = [];
            foreach ($allTransactions as $transaction) {
                if (isset($transaction['billpaymentStatus']) && 
                    $transaction['billpaymentStatus'] == '1' &&
                    isset($transaction['billExternalReferenceNo']) &&
                    !empty($transaction['billExternalReferenceNo'])) {
                    
                    $paidTransactions[$transaction['billExternalReferenceNo']] = $transaction;
                }
            }

            Log::info('Found paid transactions in ToyyibPay', ['count' => count($paidTransactions)]);

            $updatedCount = 0;
            $errors = [];

            // Check setiap unpaid bill (batch processing untuk performance)
            foreach ($unpaidBills->chunk(50) as $billChunk) {
                foreach ($billChunk as $bill) {
                    if (isset($paidTransactions[$bill->id])) {
                        try {
                            $transaction = $paidTransactions[$bill->id];
                            
                            $bill->status = 1;
                            $bill->transaction_time = $this->parseTransactionDate($transaction);
                            
                            $bill->save();
                            $updatedCount++;
                            
                            Log::info('Bill updated from system sync', [
                                'bill_id' => $bill->id,
                                'payment_date' => $bill->transaction_time,
                                'billcode' => $transaction['billCode'] ?? 'N/A'
                            ]);
                            
                        } catch (\Exception $e) {
                            $errors[] = "Failed to update bill ID {$bill->id}: " . $e->getMessage();
                            Log::error('Failed to update bill from system sync', [
                                'bill_id' => $bill->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
                
                // Small delay to prevent overwhelming the system
                if ($billChunk->count() == 50) {
                    usleep(100000); // 0.1 second delay
                }
            }

            return [
                'total_checked' => $unpaidBills->count(),
                'updated' => $updatedCount,
                'errors' => count($errors),
                'error_details' => $errors
            ];

        } catch (\Exception $e) {
            Log::error('System sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'total_checked' => $unpaidBills->count(),
                'updated' => 0,
                'errors' => 1,
                'error_details' => [$e->getMessage()]
            ];
        }
    }

    /**
     * ✅ Enhanced get payment details dengan better error handling
     */
    public function getPaymentDetails($reportId)
    {
        try {
            Log::info('Checking payment details', ['report_id' => $reportId]);
            
            $response = Http::timeout(60)->asForm()->post('https://toyyibpay.com/index.php/api/getAllBillTransactions', [
                'userSecretKey' => config('toyyibpay.key'),
                'categoryCode' => config('toyyibpay.category'),
            ]);

            if ($response->successful()) {
                $transactions = $response->json();
                
                if (!is_array($transactions)) {
                    return ['found' => false, 'error' => 'Invalid response format'];
                }
                
                foreach ($transactions as $transaction) {
                    if (isset($transaction['billExternalReferenceNo']) && 
                        $transaction['billExternalReferenceNo'] == $reportId) {
                        
                        Log::info('Payment details found', [
                            'report_id' => $reportId,
                            'status' => $transaction['billpaymentStatus'] ?? 'N/A',
                            'billcode' => $transaction['billCode'] ?? 'N/A'
                        ]);
                        
                        return [
                            'found' => true,
                            'transaction' => $transaction
                        ];
                    }
                }
                
                Log::info('Payment details not found', ['report_id' => $reportId]);
                return ['found' => false];
            }
            
            return ['found' => false, 'error' => 'API call failed: ' . $response->status()];
            
        } catch (\Exception $e) {
            Log::error('Failed to get payment details', [
                'report_id' => $reportId,
                'error' => $e->getMessage()
            ]);
            return ['found' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ✅ Batch sync untuk specific report IDs
     */
    public function syncSpecificReports(array $reportIds)
    {
        Log::info('Starting batch sync for specific reports', ['report_ids' => $reportIds]);
        
        try {
            $response = Http::timeout(120)->asForm()->post('https://toyyibpay.com/index.php/api/getAllBillTransactions', [
                'userSecretKey' => config('toyyibpay.key'),
                'categoryCode' => config('toyyibpay.category'),
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch transactions: ' . $response->status());
            }

            $allTransactions = $response->json();
            $updatedCount = 0;
            $errors = [];

            foreach ($reportIds as $reportId) {
                $found = false;
                
                foreach ($allTransactions as $transaction) {
                    if (isset($transaction['billExternalReferenceNo']) && 
                        $transaction['billExternalReferenceNo'] == $reportId &&
                        isset($transaction['billpaymentStatus']) &&
                        $transaction['billpaymentStatus'] == '1') {
                        
                        $report = ReportClass::find($reportId);
                        if ($report && $report->status != 1) {
                            try {
                                $report->status = 1;
                                $report->transaction_time = $this->parseTransactionDate($transaction);
                                $report->save();
                                $updatedCount++;
                                $found = true;
                                break;
                            } catch (\Exception $e) {
                                $errors[] = "Failed to update report ID {$reportId}: " . $e->getMessage();
                            }
                        }
                    }
                }
                
                if (!$found) {
                    Log::info('No paid transaction found for report', ['report_id' => $reportId]);
                }
            }

            return [
                'total_checked' => count($reportIds),
                'updated' => $updatedCount,
                'errors' => count($errors),
                'error_details' => $errors
            ];

        } catch (\Exception $e) {
            Log::error('Batch sync failed', ['error' => $e->getMessage()]);
            return [
                'total_checked' => count($reportIds),
                'updated' => 0,
                'errors' => 1,
                'error_details' => [$e->getMessage()]
            ];
        }
    }
}