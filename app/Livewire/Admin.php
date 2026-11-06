<?php

namespace App\Livewire;

use App\Services\Operations;
use App\Services\Reports;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Admin extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $page = 'dashboard';

    public string $search = '';

    public string $from = '';

    public string $to = '';

    public string $status = '';

    public string $paymentFilter = '';

    public ?int $selected = null;

    public ?int $editing = null;

    public string $form = '';

    public string $notice = '';

    public string $requestKey = '';

    public string $paymentKey = '';

    public array $order = [];

    public array $lines = [];

    public array $product = [];

    public array $batch = [];

    public ?int $editingBatch = null;

    public bool $batchLocked = false;

    public string $batchReason = '';

    public string $adjustDirection = 'remove';

    public ?int $reservationBatch = null;

    public string $amount = '';

    public string $paidOn = '';

    public string $reference = '';

    public string $paymentKind = 'receipt';

    public string $actionDate = '';

    public int $adjustBatch = 0;

    public string $adjustQuantity = '';

    public string $adjustReason = '';

    public string $courierInput = '0';

    public string $trackingInput = '';

    public string $notesInput = '';

    public function boot(): void
    {
        Gate::authorize('admin');
    }

    public function mount(string $page = 'dashboard'): void
    {
        abort_unless(in_array($page, ['dashboard', 'orders', 'stock', 'reports']), 404);
        $this->page = $page;
        $this->from = $page === 'orders' ? '' : today()->startOfMonth()->toDateString();
        $this->to = $page === 'orders' ? '' : today()->toDateString();
        $this->paidOn = $this->actionDate = today()->toDateString();
        $this->paymentKey = (string) Str::uuid();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        if ($this->status === 'Cancelled') {
            $this->paymentFilter = '';
        }
        $this->resetPage();
    }

    public function updatedPaymentFilter(): void
    {
        $this->resetPage();
    }

    public function closeDialog(): void
    {
        $this->form = '';
        $this->selected = null;
        $this->resetValidation();
    }

    private function dates(): void
    {
        $required = $this->page === 'orders' ? 'nullable' : 'required';
        $this->validate(['from' => $required.'|date_format:Y-m-d', 'to' => [$required, 'date_format:Y-m-d', ...($this->from ? ['after_or_equal:from'] : [])]]);
    }

    public function filter(): void
    {
        $this->dates();
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = $this->status = $this->paymentFilter = '';
        $this->from = $this->page === 'orders' ? '' : today()->startOfMonth()->toDateString();
        $this->to = $this->page === 'orders' ? '' : today()->toDateString();
        $this->resetPage();
        $this->resetValidation();
    }

    #[On('inventory-refresh')]
    public function refreshInventory(): void {}

    public function inspectReservations(int $id): void
    {
        $this->reservationBatch = $id;
    }

    public function adjustBatchStock(int $id): void
    {
        $this->adjustBatch = $id;
        $this->closeDialog();
        $this->dispatch('focus-adjustment');
    }

    public function newOrder(?int $id = null): void
    {
        $this->resetValidation();
        $this->editing = $id;
        $this->selected = null;
        $this->form = 'order';
        $this->requestKey = (string) Str::uuid();
        $this->order = ['customer' => '', 'phone' => '', 'address' => '', 'ordered_on' => today()->toDateString(), 'discount' => '0', 'delivery' => '0', 'courier' => '0', 'tracking' => '', 'notes' => ''];
        $this->lines = [['product_id' => '', 'quantity' => 1, 'price' => '0']];
        if ($id) {
            $o = DB::table('orders')->find($id);
            abort_unless($o && $o->status === 'Pending', 422);
            $this->order = (array) $o;
            foreach (['discount', 'delivery', 'courier'] as $field) {
                $this->order[$field] = Money::input($o->$field);
            }
            $this->lines = DB::table('order_items')->where('order_id', $id)->get()->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity, 'price' => Money::input($i->unit_price)])->all();
        }
    }

    public function chooseProduct(int $index): void
    {
        $p = DB::table('products')->find($this->lines[$index]['product_id'] ?? 0);
        if ($p) {
            $this->lines[$index]['price'] = Money::input($p->price);
        }
    }

    public function addLine(): void
    {
        if (count($this->lines) < 50) {
            $this->lines[] = ['product_id' => '', 'quantity' => 1, 'price' => '0'];
        }
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function saveOrder(Operations $ops): void
    {
        $this->validateMoneyFields(['order.discount', 'order.delivery', 'order.courier', ...array_map(fn ($index) => 'lines.'.$index.'.price', array_keys($this->lines))]);
        $this->validate(['order.customer' => 'required|string|max:255', 'order.phone' => 'required|string|max:40', 'order.address' => 'required|string|max:2000', 'order.ordered_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'order.tracking' => 'nullable|string|max:255', 'order.notes' => 'nullable|string|max:5000', 'lines' => 'required|array|min:1|max:50', 'lines.*.quantity' => 'required|integer|min:1|max:10000', 'lines.*.product_id' => 'required|integer|exists:products,id', 'lines.*.price' => 'required', 'order.discount' => 'required', 'order.delivery' => 'required', 'order.courier' => 'required']);
        $id = $ops->saveOrder($this->order, $this->lines, $this->requestKey, $this->editing);
        $this->form = '';
        $this->viewOrder($id);
        $this->notice = 'Order saved and stock reserved.';
        $this->dispatch('inventory-changed');
    }

    public function viewOrder(int $id): void
    {
        abort_unless(DB::table('orders')->where('id', $id)->exists(), 404);
        $this->selected = $id;
        $this->form = '';
        $this->paymentKey = (string) Str::uuid();
        $this->amount = '';
        $this->reference = '';
        $this->resetValidation();
        $order = DB::table('orders')->find($id);
        $this->courierInput = Money::input($order->courier);
        $this->trackingInput = $order->tracking ?? '';
        $this->notesInput = $order->notes ?? '';
    }

    public function saveOperationalDetails(Operations $ops): void
    {
        $this->validateMoneyFields(['courierInput']);
        $this->validate(['selected' => 'required|integer|exists:orders,id', 'trackingInput' => 'nullable|string|max:255', 'notesInput' => 'nullable|string|max:5000']);
        $cost = Money::parse($this->courierInput);
        $ops->transaction(fn () => DB::table('orders')->where('id', $this->selected)->update(['courier' => $cost, 'tracking' => $this->trackingInput, 'notes' => $this->notesInput, 'updated_at' => now()]));
        $this->notice = 'Courier cost, tracking and private notes updated. Issued invoices retain their snapshots.';
    }

    public function fulfil(string $status, Operations $ops): void
    {
        $this->validate(['selected' => 'required|integer|exists:orders,id', 'actionDate' => 'required|date_format:Y-m-d']);
        $ops->transition($this->selected, $status, $this->actionDate);
        $this->notice = 'Fulfilment updated.';
        $this->dispatch('inventory-changed');
    }

    public function returnStock(Operations $ops): void
    {
        $this->validate(['selected' => 'required|integer|exists:orders,id']);
        $ops->returnStock($this->selected);
        $this->notice = 'Return recorded; physical stock restored.';
        $this->dispatch('inventory-changed');
    }

    public function recordPayment(Operations $ops): void
    {
        $this->validateMoneyFields(['amount']);
        $this->validate(['selected' => 'required|integer|exists:orders,id', 'paidOn' => 'required|date_format:Y-m-d', 'reference' => 'nullable|string|max:255']);
        $ops->payment($this->selected, $this->paymentKind, $this->amount, $this->paidOn, $this->reference, $this->paymentKey);
        $this->paymentKey = (string) Str::uuid();
        $this->amount = '';
        $this->notice = 'Payment history updated.';
    }

    public function invoice(bool $correct = false): void
    {
        $this->validate(['selected' => 'required|integer|exists:orders,id']);
        $id = app(Operations::class)->issueInvoice($this->selected, $correct);
        $this->redirectRoute('invoice', ['id' => $id]);
    }

    public function productForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->form = 'product';
        $this->selected = null;
        $this->product = ['id' => $id, 'name' => '', 'kind' => 'honey', 'stock_key' => '', 'pack_quantity' => 500, 'price' => '0', 'low_stock' => 0];
        if ($id) {
            $p = DB::table('products')->find($id);
            abort_unless($p, 404);
            $this->product = (array) $p;
            $this->product['price'] = Money::input($p->price);
        }
    }

    public function saveProduct(Operations $ops): void
    {
        $this->validateMoneyFields(['product.price']);
        $this->validate(['product.name' => 'required|string|max:255', 'product.kind' => 'required|in:honey,snack', 'product.stock_key' => 'required|alpha_dash|max:100', 'product.pack_quantity' => 'required|integer|min:1|max:100000', 'product.low_stock' => 'required|integer|min:0|max:100000000']);
        if ($this->product['kind'] === 'snack' && (int) $this->product['pack_quantity'] !== 1) {
            $this->addError('operation', 'Snack pack quantity must be 1 whole unit.');

            return;
        }
        $price = Money::parse($this->product['price']);
        $ops->transaction(function () use ($price) {
            $id = $this->product['id'] ?? null;
            $values = array_intersect_key($this->product, array_flip(['name', 'kind', 'stock_key', 'pack_quantity', 'low_stock']));
            if (DB::table('products')->where('stock_key', $values['stock_key'])->where('kind', '!=', $values['kind'])->exists()) {
                throw ValidationException::withMessages(['operation' => 'A stock pool cannot mix grams and whole units.']);
            }
            if ($id && (DB::table('order_items')->where('product_id', $id)->exists() || DB::table('batches')->where('stock_key', DB::table('products')->where('id', $id)->value('stock_key'))->exists())) {
                $old = DB::table('products')->find($id);
                foreach (['kind', 'stock_key', 'pack_quantity'] as $field) {
                    if ((string) $old->$field !== (string) $values[$field]) {
                        throw ValidationException::withMessages(['operation' => 'Used products keep their stock pool and pack size. Create another product for a new size.']);
                    }
                }
            }
            if ($id) {
                DB::table('products')->where('id', $id)->update($values + ['price' => $price, 'updated_at' => now()]);
            } else {
                DB::table('products')->insert($values + ['price' => $price, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        $this->form = '';
        $this->notice = 'Product saved.';
    }

    public function batchForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->selected = null;
        $this->form = 'batch';
        $this->editingBatch = $id;
        $this->batchLocked = false;
        $this->batchReason = '';
        $this->batch = ['reference' => '', 'stock_key' => '', 'received_on' => today()->toDateString(), 'original_quantity' => '', 'purchase' => '0', 'packaging' => '0', 'branding' => '0', 'other' => '0'];
        if ($id) {
            $b = DB::table('batches')->find($id);
            abort_unless($b, 404);
            $this->batch = array_intersect_key((array) $b, $this->batch);
            foreach (['purchase', 'packaging', 'branding', 'other'] as $cost) {
                $this->batch[$cost] = Money::input($b->$cost);
            }
            $this->batchLocked = app(Operations::class)->batchUsed($id);
        }
    }

    public function saveBatch(Operations $ops): void
    {
        $this->validate(['editingBatch' => 'required|integer|exists:batches,id', 'batchReason' => 'required|string|min:5|max:2000']);
        $ops->editBatch($this->editingBatch, $this->batch, $this->batchReason);
        $this->form = '';
        $this->notice = 'Batch updated. Current stock and investment refreshed.';
        $this->dispatch('inventory-changed');
    }

    public function receiveBatch(Operations $ops): void
    {
        $this->validateMoneyFields(['batch.purchase', 'batch.packaging', 'batch.branding', 'batch.other']);
        $this->validate(['batch.reference' => ['required', 'string', 'max:100', Rule::unique('batches', 'reference')], 'batch.stock_key' => 'required|exists:products,stock_key', 'batch.received_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'batch.original_quantity' => 'required|integer|min:1|max:100000000']);
        $values = $this->batch;
        foreach (['purchase', 'packaging', 'branding', 'other'] as $field) {
            $values[$field] = Money::parse($values[$field]);
        }
        $ops->transaction(fn () => DB::table('batches')->insert($values + ['physical' => $values['original_quantity'], 'created_at' => now(), 'updated_at' => now()]));
        $this->form = '';
        $this->notice = 'Batch received and available for orders.';
        $this->dispatch('inventory-changed');
    }

    public function adjust(Operations $ops): void
    {
        $this->validate(['adjustBatch' => 'required|integer|exists:batches,id', 'adjustDirection' => 'required|in:remove,restore', 'adjustQuantity' => 'required|integer|min:1|max:100000000', 'adjustReason' => 'required|string|min:5|max:2000']);
        try {
            $ops->adjust($this->adjustBatch, (int) $this->adjustQuantity * ($this->adjustDirection === 'remove' ? -1 : 1), $this->adjustReason);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['adjustQuantity' => $e->getMessage()]);
        }
        $this->adjustQuantity = '';
        $this->adjustReason = '';
        $this->notice = 'Documented stock adjustment saved.';
        $this->dispatch('inventory-changed');
    }

    public function render()
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->from) ? $this->from : today()->startOfMonth()->toDateString();
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->to) ? $this->to : today()->toDateString();
        $reports = new Reports;
        $ops = new Operations;
        $payments = DB::table('receipts')->select('order_id')->selectRaw("SUM(CASE WHEN kind='receipt' THEN amount ELSE -amount END) net")->groupBy('order_id');
        $query = DB::table('orders as o')->leftJoinSub($payments, 'p', 'p.order_id', '=', 'o.id')->select('o.*', DB::raw('COALESCE(p.net,0) as paid'));
        if ($this->page !== 'orders') {
            $query->whereBetween('o.ordered_on', [$from, $to]);
        } else {
            if ($this->from) {
                $query->whereDate('o.ordered_on', '>=', $this->from);
            }
            if ($this->to) {
                $query->whereDate('o.ordered_on', '<=', $this->to);
            }
        }
        if ($this->search !== '') {
            $query->where(fn ($q) => $q->where('o.number', 'like', '%'.$this->search.'%')->orWhere('o.customer', 'like', '%'.$this->search.'%'));
        }
        if ($this->status) {
            $query->where('o.status', $this->status);
        }
        if ($this->paymentFilter) {
            $query->where('o.status', '!=', 'Cancelled')->whereRaw($this->paymentFilter === 'Paid' ? 'COALESCE(p.net,0)>=o.total' : 'COALESCE(p.net,0)<o.total');
        }
        $selected = $this->selected ? DB::table('orders')->find($this->selected) : null;
        $bars = DB::table('orders')->where('status', 'Delivered')->whereBetween('delivered_on', [$from, $to])->select('delivered_on')->selectRaw('SUM(total) revenue')->groupBy('delivered_on')->orderBy('delivered_on')->get();

        return view('livewire.admin', ['summary' => $reports->summary($from, $to), 'orders' => $query->orderByDesc('o.id')->paginate($this->page === 'dashboard' ? 4 : 12), 'products' => DB::table('products')->orderBy('name')->get(), 'batches' => $reports->batches($from, $to), 'selectedOrder' => $selected, 'items' => $selected ? DB::table('order_items')->where('order_id', $selected->id)->get() : collect(), 'allocations' => $selected ? $ops->allocations($selected->id) : collect(), 'receipts' => $selected ? DB::table('receipts')->where('order_id', $selected->id)->orderBy('id')->get() : collect(), 'netPaid' => $selected ? $ops->paid($selected->id) : 0, 'issued' => $selected ? DB::table('invoices')->where('order_id', $selected->id)->orderByDesc('version')->get() : collect(), 'bars' => $bars, 'statuses' => DB::table('orders')->whereBetween('ordered_on', [$from, $to])->select('status')->selectRaw('COUNT(*) count')->groupBy('status')->pluck('count', 'status'), 'dispatchCount' => DB::table('orders')->where('status', 'Pending')->count()])->layout('layouts.app', ['page' => $this->page]);
    }

    private function validateMoneyFields(array $fields): void
    {
        $messages = [];
        foreach ($fields as $field) {
            try {
                Money::parse(data_get($this, $field));
            } catch (ValidationException) {
                $messages[$field] = 'Enter a valid PKR amount with up to two decimal places.';
            }
        }
        if ($messages) {
            throw ValidationException::withMessages($messages);
        }
    }
}
