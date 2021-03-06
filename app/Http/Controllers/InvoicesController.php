<?php

namespace App\Http\Controllers;

use App\Models\Banks;
use App\Models\invoices;
use App\Models\invoices_attachements;
use Illuminate\Http\Request;
use App\Models\invoices_details;
use App\Models\User;
use App\Notifications\AddInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    function __construct()
    {

        $this->middleware('permission:عرض صلاحية', ['only' => ['index']]);
        $this->middleware('permission:اضافة صلاحية', ['only' => ['create', 'store']]);
        $this->middleware('permission:تعديل صلاحية', ['only' => ['edit', 'update']]);
        $this->middleware('permission:حذف صلاحية', ['only' => ['destroy']]);
    }

    public function index()
    {
        $invoices = invoices::all();
        return view('invoices.index', compact('invoices'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $banks = Banks::all();
        return view('invoices.add_invoice', compact('banks'));

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        invoices::create([
            'invoice_number' => $request->invoice_number,
            'invoice_Date' => $request->invoice_Date,
            'Due_date' => $request->Due_date,
            'product' => $request->product,
            'bank_id' => $request->Bank,
            'Amount_collection' => $request->Amount_collection,
            'Amount_Commission' => $request->Amount_Commission,
            'Discount' => $request->Discount,
            'Value_VAT' => $request->Value_VAT,
            'Rate_VAT' => $request->Rate_VAT,
            'Total' => $request->Total,
            'Status' => 'غير مدفوعة',
            'Value_Status' => 2,
            'note' => $request->note,
        ]);

        $invoice_id = invoices::latest()->first()->id;

        invoices_details::create([
            'id_Invoice' => $invoice_id,
            'invoice_number' => $request->invoice_number,
            'product' => $request->product,
            'Bank' => $request->Bank,
            'Status' => 'غير مدفوعة',
            'Value_Status' => 2,
            'note' => $request->note,
            'user' => (Auth::user()->name),
        ]);

    try {


        if ($request->hasFile('pic')) {

            $invoice_id = invoices::latest()->first()->id;
            $image = $request->file('pic');
            $file_name = $image->getClientOriginalName();
            $invoice_number = $request->invoice_number;

            $attachments = new invoices_attachements();
            $attachments->file_name = $file_name;
            $attachments->invoice_number = $invoice_number;
            $attachments->Created_by = Auth::user()->name;
            $attachments->id_Invoice = $invoice_id;
            $attachments->save();

            // move pic
            $imageName = $request->pic->getClientOriginalName();
            
            $request->pic->move(public_path('Attachments/' . $invoice_number), $imageName);
        }
    } catch (\Exception $e) {
        return redirect()->back()->withErrors(['error' => $e->getMessage()]);
    }

    $user = User::first();
    Notification::send($user, new AddInvoice($invoice_id));

    return redirect('invoices')->with('Add', 'تم اضافة الفاتورة بنجاح');



         

        
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\invoices  $invoices
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $invoices = invoices::where('id', $id)->first();
        return view('invoices.status_update', compact('invoices'));

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\invoices  $invoices
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $invoices = invoices::where('id', $id)->first();
        $banks = Banks::all();
        return view('invoices.edit_invoices', compact('banks', 'invoices',));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\invoices  $invoices
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $invoices = invoices::findOrFail($id);
        $invoices->update([
            'invoice_number' => $request->invoice_number,
            'invoice_Date' => $request->invoice_Date,
            'Due_date' => $request->Due_date,
            'product' => $request->product,
            'bank_id' => $request->Bank,
            'Amount_collection' => $request->Amount_collection,
            'Amount_Commission' => $request->Amount_Commission,
            'Discount' => $request->Discount,
            'Value_VAT' => $request->Value_VAT,
            'Rate_VAT' => $request->Rate_VAT,
            'Total' => $request->Total,
            'note' => $request->note,
        ]);

        return redirect('invoices')->with('edit', 'تم تعديل الفاتورة بنجاح');    
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\invoices  $invoices
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $invoices_id = invoices::where('id', $id)->first(); 

        $Details = invoices_attachements::where('id_Invoice', $id)->first(); // id لانه first



        $id_page =$request->id_page;

        if (!$id_page==2) { // not come from archive

            if (!empty($Details->invoice_number)) {

                Storage::disk('public_uploads')->deleteDirectory($Details->invoice_number);
            }

        $invoices_id->forceDelete();
        return redirect('invoices')->with('delete', 'تم حذف الفاتورة بنجاح');    


        }

        else {

            $invoices_id->delete(); // soft delete
            return redirect('Archive')->with('edit', 'تم ارشفة الفاتورة بنجاح');    

        }

    }
    public function getproducts($id)
    {
        $products = DB::table("products")->where("bank_id", $id)->pluck("Product_name", "id");
        return json_encode($products);
    }

    public function Status_Update($id, Request $request)
    {
        $invoices = invoices::findOrFail($id);

        if ($request->Status === 'مدفوعة') {

            $invoices->update([
                'Value_Status' => 1,
                'Status' => $request->Status,
                'Payment_Date' => $request->Payment_Date,
            ]);

            invoices_Details::create([
                'id_Invoice' => $request->invoice_id,
                'invoice_number' => $request->invoice_number,
                'product' => $request->product,
                'Bank' => $request->Bank,
                'Status' => $request->Status,
                'Value_Status' => 1,
                'note' => $request->note,
                'Payment_Date' => $request->Payment_Date,
                'user' => (Auth::user()->name),
            ]);
        }

        else {
            $invoices->update([
                'Value_Status' => 3,
                'Status' => $request->Status,
                'Payment_Date' => $request->Payment_Date,
            ]);
            invoices_Details::create([
                'id_Invoice' => $request->invoice_id,
                'invoice_number' => $request->invoice_number,
                'product' => $request->product,
                'Bank' => $request->Bank,
                'Status' => $request->Status,
                'Value_Status' => 3,
                'note' => $request->note,
                'Payment_Date' => $request->Payment_Date,
                'user' => (Auth::user()->name),
            ]);
        }
        return redirect('invoices')->with('edit', 'تم تغير حالة الدفع بنجاح');

    }

    public function Invoice_Paid()
    {
        $invoices = invoices::where('Value_Status', 1)->get();
        return view('invoices.Invoice_Paid',compact('invoices'));
    }

    public function Invoice_unPaid()
    {
        $invoices = invoices::where('Value_Status',2)->get();
        return view('invoices.Invoice_unPaid',compact('invoices'));
    }

    public function Invoice_Partial()
    {
        $invoices = invoices::where('Value_Status',3)->get();
        return view('invoices.Invoice_Partial',compact('invoices'));
    }

    public function Print_invoice($id)
    {
        $invoices = invoices::where('id', $id)->first();
        return view('invoices.Print_invoice',compact('invoices'));
    }





}
