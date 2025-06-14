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
        Log::info($response);
    }

   public function billTransaction($billCode)
{
    $response = Http::asForm()->post('https://toyyibpay.com/index.php/api/getBillTransactions', [
        'userSecretKey' => config('toyyibpay.key'),
        'billCode' => $billCode,
    ]);

    if ($response->successful()) {
        $transactions = $response->json();

        foreach ($transactions as $trx) {
            if ($trx['status'] == 1) {
                $item = ReportClass::find($trx['externalReferenceNo']); // guna ID asal bil

                if ($item && $item->status != 1) {
                    $item->status = 1;
                    $item->transaction_time = $trx['payDate'] ?? now(); // GUNA transaction_time
                    $item->save();
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi telah disemak dan dikemaskini.',
        ]);
    }

    return response()->json([
        'status' => 'error',
        'message' => 'Gagal semak transaksi dari ToyyibPay',
    ], 500);
}

}
