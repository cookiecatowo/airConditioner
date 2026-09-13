<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::query()
            ->when($request->search, function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('tax_id', 'like', "%{$search}%");
                });
            })
            ->withCount('orders')
            ->withSum('orders', 'total_amount')
            ->orderByDesc('orders_count')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters'   => ['search' => $request->search],
        ]);
    }

    public function show(Customer $customer)
    {
        $customer->loadCount('orders');

        return Inertia::render('Customers/Show', [
            'customer'  => $customer,
            'addresses' => $customer->knownAddresses(),
            'orders'    => $customer->orders()
                ->orderByDesc('date')->orderByDesc('id')
                ->get(['id', 'date', 'type', 'work_category', 'address', 'total_amount', 'processing_status', 'payment_status', 'report_title']),
            'others'    => Customer::where('id', '!=', $customer->id)
                ->withCount('orders')->orderBy('name')->get(['id', 'name', 'phone']),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:100', Rule::unique('customers')->ignore($customer->id)],
            'phone'  => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:20'],
        ], [
            'name.unique' => '已經有另一位顧客用這個名稱了，請改用別的名稱，或把兩筆合併起來。',
        ]);

        DB::transaction(function () use ($customer, $data) {
            $customer->update($data);
            // 統編屬於顧客，改了就同步到這位顧客的所有報價單
            $customer->orders()->update(['tax_id' => $data['tax_id']]);
        });

        return back();
    }

    /** 把 $customer 併入 target，訂單全部轉移後刪除 $customer */
    public function merge(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'target_id' => ['required', 'integer', 'exists:customers,id', Rule::notIn([$customer->id])],
        ], [
            'target_id.not_in' => '不能跟自己合併。',
        ]);

        $target = Customer::findOrFail($data['target_id']);

        DB::transaction(function () use ($customer, $target) {
            $customer->orders()->update(['customer_id' => $target->id]);

            foreach (['phone', 'tax_id'] as $field) {
                if (blank($target->$field) && filled($customer->$field)) {
                    $target->$field = $customer->$field;
                }
            }
            $target->save();
            $target->orders()->update(['tax_id' => $target->tax_id]);

            $customer->delete();
        });

        return redirect()->route('customers.show', $target)
            ->with('success', "已將「{$customer->name}」併入「{$target->name}」");
    }

    public function destroy(Customer $customer)
    {
        if ($customer->orders()->exists()) {
            return back()->withErrors(['delete' => '這位顧客底下還有報價單，不能刪除。請先合併到其他顧客。']);
        }

        $customer->delete();

        return redirect()->route('customers.index');
    }
}
