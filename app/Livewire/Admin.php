<?php

namespace App\Livewire;

use App\Services\BatchCorrections;
use App\Services\Customers;
use App\Services\Operations;
use App\Services\OrderCorrections;
use App\Services\PaymentCorrections;
use App\Services\Reports;
use App\Services\Savings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Admin extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Locked]
    public array $amountReview = [];

    #[Locked]
    public string $amountFingerprint = '';

    #[Locked]
    public string $amountKey = '';

    #[Locked]
    public string $pendingFingerprint = '';

    #[Locked]
    public ?int $amountOrder = null;

    public array $amountEdit = [];

    public string $amountReason = '';

    #[Locked]
    public array $savingsState = [];

    #[Locked]
    public array $savingsReview = [];

    #[Locked]
    public string $savingsKey = '';

    public array $savingsEdit = [];

    public string $savingsFrom = '';

    public string $savingsTo = '';

    public function editAmounts(int $id): void
    {
        $service = app(OrderCorrections::class);
        $snapshot = $service->snapshot($id);
        $this->amountOrder = $id;
        $this->amountFingerprint = $service->fingerprint($snapshot);
        $this->amountKey = (string) Str::uuid();
        $this->amountEdit = ['prices' => []];
        foreach (['discount', 'delivery', 'courier'] as $field) {
            $this->amountEdit[$field] = Money::input($snapshot['order'][$field]);
        }
        foreach ($snapshot['items'] as $item) {
            $this->amountEdit['prices'][$item['id']] = Money::input($item['unit_price']);
        }
        $this->amountReason = '';
        $this->amountReview = [];
        $this->selected = null;
        $this->form = 'order-amounts';
        $this->resetValidation();
    }

    public function previewAmounts(): void
    {
        $this->amountReview = [];
        $this->amountReview = app(OrderCorrections::class)->preview($this->amountOrder, $this->amountEdit, $this->amountReason, $this->amountFingerprint);
    }

    public function saveAmounts(): void
    {
        if (! $this->amountReview) {
            $this->addError('operation', 'Preview the correction first.');

            return;
        }
        app(OrderCorrections::class)->save($this->amountOrder, $this->amountEdit, $this->amountReason, $this->amountKey, $this->amountReview);
        $this->viewOrder($this->amountOrder);
        $this->notice = 'Order amounts corrected. Payments and issued invoice versions are retained.';
    }

    public function openSavings(string $type = 'history', ?int $entry = null): void
    {
        $this->selected = null;
        $this->form = $type === 'history' ? 'savings-history' : 'savings';
        $this->savingsState = app(Savings::class)->state();
        $this->savingsReview = [];
        $this->savingsKey = (string) Str::uuid();
        $this->savingsEdit = ['type' => $type, 'entry' => $entry, 'amount' => $type === 'count' ? Money::input($this->savingsState['balance']) : '', 'date' => today()->toDateString(), 'note' => ''];
        $this->resetValidation();
    }

    public function previewSavings(): void
    {
        $this->savingsReview = [];
        $this->savingsReview = app(Savings::class)->preview($this->savingsEdit, $this->savingsState);
    }

    public function saveSavings(): void
    {
        if (! $this->savingsReview) {
            $this->addError('operation', 'Preview the movement first.');

            return;
        }
        app(Savings::class)->save($this->savingsEdit, $this->savingsKey, $this->savingsReview);
        $this->openSavings('history');
        $this->notice = 'Savings movement recorded with its audit history.';
    }

    public string $page = 'dashboard';

    #[Url(as: 'tab', except: 'overview')]
    public string $dashboardTab = 'overview';

    public string $customerSearch = '';

    public bool $saveCustomerProfile = false;

    public bool $confirmDistinctCustomer = false;

    public bool $confirmCustomerChange = false;

    #[Locked]
    public ?int $customerId = null;

    public array $profile = [];

    public bool $reviewedCustomer = false;

    public string $breakdownKind = 'cash';

    #[Locked]
    public ?int $editingPayment = null;

    #[Locked]
    public string $correctionKey = '';

    #[Locked]
    public array $paymentPreview = [];

    public array $paymentEdit = [];

    public string $paymentReason = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public string $from = '';

    public string $to = '';

    #[Locked]
    public string $appliedFrom = '';

    #[Locked]
    public string $appliedTo = '';

    #[Locked]
    public array $batchPreview = [];

    #[Locked]
    public string $batchKey = '';

    public string $stockCount = '';

    public ?int $historyBatch = null;

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'payment', except: '')]
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
        abort_unless(in_array($page, ['dashboard', 'orders', 'stock', 'reports', 'customers']), 404);
        $this->page = $page;
        $this->from = $this->to = '';
        $requested = request()->only('from', 'to');
        if ($requested && ! validator($requested, ['from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', ...(! empty($requested['from']) ? ['after_or_equal:from'] : [])]])->fails()) {
            $this->from = (string) ($requested['from'] ?? '');
            $this->to = (string) ($requested['to'] ?? '');
            $this->appliedFrom = $this->from;
            $this->appliedTo = $this->to;
        }
        $this->status = in_array(request('status'), ['Pending', 'Dispatched', 'Delivered', 'Cancelled']) ? request('status') : '';
        $this->paymentFilter = in_array(request('payment'), ['Pending', 'Paid']) ? request('payment') : '';
        $this->paidOn = $this->actionDate = today()->toDateString();
        $this->paymentKey = (string) Str::uuid();
        if (! in_array($this->dashboardTab, ['overview', 'charts', 'finance', 'stock', 'customers'])) {
            $this->dashboardTab = 'overview';
        }
        if ($page === 'customers' && ctype_digit((string) request('customer'))) {
            $this->viewCustomer((int) request('customer'));
        }
    }

    public function selectDashboardTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'charts', 'finance', 'stock', 'customers'])) {
            $this->dashboardTab = $tab;
        }
    }

    public function selectCustomer(int $id): void
    {
        $c = DB::table('customers')->find($id);
        abort_unless($c, 404);
        $this->order['customer_id'] = $c->id;
        $this->order['customer'] = $c->name;
        $this->order['phone'] = $c->phone;
        $this->order['address'] = $c->address;
        $this->customerSearch = '';
        $this->saveCustomerProfile = false;
        $this->confirmCustomerChange = false;
        $this->resetValidation();
    }

    public function newCustomerEntry(): void
    {
        $this->order['customer_id'] = null;
        $this->order['customer'] = '';
        $this->order['phone'] = '';
        $this->order['address'] = '';
        $this->customerSearch = '';
        $this->saveCustomerProfile = false;
        $this->confirmDistinctCustomer = false;
        $this->confirmCustomerChange = false;
    }

    public function viewCustomer(int $id): void
    {
        abort_unless(DB::table('customers')->where('id', $id)->exists(), 404);
        $this->customerId = $id;
        $this->selected = null;
        $this->form = 'customer';
        $this->resetPage('customerOrdersPage');
        $this->resetValidation();
    }

    public function editCustomer(int $id): void
    {
        $this->viewCustomer($id);
        $this->form = 'customer-edit';
        $c = DB::table('customers')->find($id);
        $this->profile = ['name' => $c->name, 'phone' => $c->phone, 'address' => $c->address];
        $this->reviewedCustomer = (bool) $c->reviewed_at;
    }

    public function saveCustomer(Customers $customers): void
    {
        $this->validate(['profile.name' => 'required|string|max:255', 'profile.phone' => 'required|string|max:40', 'profile.address' => 'required|string|max:2000']);
        $customers->update($this->customerId, $this->profile, $this->reviewedCustomer);
        $this->viewCustomer($this->customerId);
        $this->notice = 'Customer profile saved for future orders. Existing order details and issued invoices retain their snapshots.';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->resetPage('customersPage');
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

    public function showBreakdown(string $kind): void
    {
        abort_unless(in_array($kind, ['sales', 'cash', 'pending', 'investment', 'profit', 'packs']), 404);
        $this->breakdownKind = $kind;
        $this->form = 'breakdown';
        $this->selected = null;
        $this->resetPage('detailPage');
        $this->resetValidation();
    }

    public function editPayment(int $id): void
    {
        $payment = DB::table('receipts')->find($id);
        abort_unless($payment, 404);
        $this->selected = null;
        $this->editingPayment = $id;
        $this->form = 'payment-correction';
        $this->paymentEdit = ['amount' => Money::input($payment->amount), 'paid_on' => $payment->paid_on, 'reference' => $payment->reference ?? ''];
        $this->paymentReason = '';
        $this->paymentPreview = [];
        $this->correctionKey = (string) Str::uuid();
        $this->resetValidation();
    }

    public function previewPayment(PaymentCorrections $corrections): void
    {
        $this->resetValidation();
        $this->paymentPreview = [];
        $this->paymentPreview = $corrections->preview($this->editingPayment, $this->paymentEdit, $this->paymentReason);
    }

    public function savePaymentCorrection(PaymentCorrections $corrections): void
    {
        if (! $this->paymentPreview) {
            $this->addError('paymentReason', 'Review the preview before confirming.');

            return;
        }
        $corrections->save($this->editingPayment, $this->paymentEdit, $this->paymentReason, $this->correctionKey, $this->paymentPreview['fingerprint']);
        $this->notice = 'Payment entry corrected with an audit revision. Cash and balances refreshed; issued invoices keep their original snapshots.';
        $this->paymentPreview = [];
        $this->showBreakdown('cash');
        $this->dispatch('inventory-changed');
    }

    private function dates(): void
    {
        $required = 'nullable';
        $this->validate(['from' => $required.'|date_format:Y-m-d', 'to' => [$required, 'date_format:Y-m-d', ...($this->from ? ['after_or_equal:from'] : [])]]);
    }

    public function filter(): void
    {
        $this->dates();
        $this->appliedFrom = $this->from;
        $this->appliedTo = $this->to;
        $this->resetPage();
        $this->resetPage('batchesPage');
        $this->resetPage('detailPage');
        $this->dispatch('date-range-applied', range: ['from' => $this->appliedFrom, 'to' => $this->appliedTo]);
    }

    public function resetFilters(): void
    {
        $this->search = $this->status = $this->paymentFilter = '';
        $this->clearDates();
        $this->resetPage();
        $this->resetValidation();
    }

    public function clearDates(): void
    {
        $this->from = $this->to = $this->appliedFrom = $this->appliedTo = '';
        $this->resetPage();
        $this->resetPage('batchesPage');
        $this->resetPage('detailPage');
        $this->resetValidation(['from', 'to']);
        $this->dispatch('date-range-applied', range: ['from' => '', 'to' => '']);
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
        $this->customerSearch = '';
        $this->saveCustomerProfile = false;
        $this->confirmDistinctCustomer = false;
        $this->confirmCustomerChange = false;
        $this->selected = null;
        $this->form = 'order';
        $this->requestKey = (string) Str::uuid();
        $this->order = ['customer' => '', 'phone' => '', 'address' => '', 'ordered_on' => today()->toDateString(), 'discount' => '0', 'delivery' => '0', 'courier' => '0', 'tracking' => '', 'notes' => ''];
        $this->lines = [['product_id' => '', 'quantity' => 1, 'price' => '0']];
        if ($id) {
            $o = DB::table('orders')->find($id);
            abort_unless($o && $o->status === 'Pending', 422);
            $service = app(OrderCorrections::class);
            $this->pendingFingerprint = $service->fingerprint($service->snapshot($id));
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
        $data = $this->order + ['customer_id' => null];
        $data['save_customer'] = $this->saveCustomerProfile;
        $data['confirm_distinct'] = $this->confirmDistinctCustomer;
        $data['confirm_customer_change'] = $this->confirmCustomerChange;
        $id = $ops->transaction(function () use ($ops, $data) {
            if ($this->editing) {
                $service = app(OrderCorrections::class);
                if (! hash_equals($this->pendingFingerprint, $service->fingerprint($service->snapshot($this->editing)))) {
                    throw ValidationException::withMessages(['operation' => 'Order changed. Reopen the form before saving.']);
                }
                $old = DB::table('orders')->find($this->editing);
                foreach (['discount', 'delivery', 'courier'] as $field) {
                    if (Money::parse($data[$field]) !== (int) $old->$field) {
                        throw ValidationException::withMessages(['operation' => 'Use Edit Order to correct amounts with a reason and preview.']);
                    }
                }
                $prices = DB::table('order_items')->where('order_id', $this->editing)->get()->groupBy('product_id');
                foreach ($this->lines as $line) {
                    if (isset($prices[$line['product_id']]) && ! $prices[$line['product_id']]->contains(fn ($item) => (int) $item->unit_price === Money::parse($line['price']))) {
                        throw ValidationException::withMessages(['operation' => 'Use Edit Order to correct existing unit prices with a reason and preview.']);
                    }
                }
            }

            return $ops->saveOrder($data, $this->lines, $this->requestKey, $this->editing);
        });
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
        if ($cost !== (int) DB::table('orders')->where('id', $this->selected)->value('courier')) {
            $this->editAmounts($this->selected);
            $this->amountEdit['courier'] = Money::input($cost);
            $this->notice = 'Enter a correction reason and preview the courier change. Tracking and notes have not been saved.';

            return;
        }
        $ops->transaction(fn () => DB::table('orders')->where('id', $this->selected)->update(['tracking' => $this->trackingInput, 'notes' => $this->notesInput, 'updated_at' => now()]));
        $this->notice = 'Tracking and private notes updated.';
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
        $this->notice = 'Invoice version issued. Use View Invoice to open it in a new tab.';
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
        $this->batchReason = $this->stockCount = '';
        $this->batchPreview = [];
        $this->batchKey = (string) Str::uuid();
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

    public function previewBatch(BatchCorrections $corrections): void
    {
        $this->resetValidation();
        $this->batchPreview = [];
        $this->validate(['editingBatch' => 'required|exists:batches,id']);
        $this->batchPreview = $corrections->preview($this->editingBatch, $this->batch, $this->batchReason, $this->stockCount);
    }

    public function saveBatch(BatchCorrections $corrections): void
    {
        if (! $this->batchPreview) {
            $this->addError('batchReason', 'Review the correction preview before confirming.');

            return;
        }
        $corrections->save($this->editingBatch, $this->batch, $this->batchReason, $this->stockCount, $this->batchKey, $this->batchPreview['fingerprint']);
        $this->form = '';
        $this->batchPreview = [];
        $this->notice = 'Batch correction saved. Stock and internal costs updated; issued invoices are unchanged. View History for the revision.';
        $this->dispatch('inventory-changed');
    }

    public function viewBatchHistory(int $id): void
    {
        abort_unless(DB::table('batches')->where('id', $id)->exists(), 404);
        $this->historyBatch = $id;
        $this->selected = null;
        $this->form = 'history';
    }

    public function receiveBatch(Operations $ops): void
    {
        $this->validateMoneyFields(['batch.purchase', 'batch.packaging', 'batch.branding', 'batch.other']);
        $this->validate(['batch.reference' => ['required', 'string', 'max:100', Rule::unique('batches', 'reference')], 'batch.stock_key' => 'required|exists:products,stock_key', 'batch.received_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'batch.original_quantity' => 'required|integer|min:1|max:100000000']);
        $values = $this->batch;
        foreach (['purchase', 'packaging', 'branding', 'other'] as $field) {
            $values[$field] = Money::parse($values[$field]);
        }
        $ops->transaction(function () use ($values) {
            $id = DB::table('batches')->insertGetId($values + ['physical' => $values['original_quantity'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('batch_changes')->insert(['batch_id' => $id, 'user_id' => auth()->id(), 'kind' => 'created', 'reason' => 'Stock received', 'before' => '{}', 'after' => json_encode(DB::table('batches')->find($id)), 'created_at' => now()]);
        });
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
        $from = $this->appliedFrom ?: '0001-01-01';
        $to = $this->appliedTo ?: '9999-12-31';
        $reports = new Reports;
        $ops = new Operations;
        $payments = DB::table('receipts')->select('order_id')->selectRaw("SUM(CASE WHEN kind='receipt' THEN amount ELSE -amount END) net")->groupBy('order_id');
        $query = DB::table('orders as o')->leftJoinSub($payments, 'p', 'p.order_id', '=', 'o.id')->select('o.*', DB::raw('COALESCE(p.net,0) as paid'));
        $query->whereBetween('o.ordered_on', [$from, $to]);
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
        $latestInvoices = DB::table('invoices')->select('order_id')->selectRaw('MAX(id) invoice_id')->groupBy('order_id')->pluck('invoice_id', 'order_id');

        return view('livewire.admin', ['latestInvoices' => $latestInvoices, 'customers' => (new Customers)->listing($this->search)->orderBy('c.name')->orderBy('c.id')->paginate(25, pageName: 'customersPage'), 'customerMatches' => mb_strlen($this->customerSearch) >= 2 ? (new Customers)->listing(trim($this->customerSearch))->orderBy('c.name')->orderBy('c.id')->limit(8)->get() : collect(), 'customerOrders' => $this->customerId ? (new Reports)->balances()->where('o.customer_id', $this->customerId)->orderByDesc('o.id')->paginate(10, pageName: 'customerOrdersPage') : null, 'topCustomers' => (new Customers)->rankings($from, $to), 'packSummary' => $reports->packs($from, $to), 'breakdownRows' => $this->form === 'breakdown' ? $reports->breakdown($this->breakdownKind, $from, $to) : null, 'summary' => $reports->summary($from, $to), 'orders' => ($this->page === 'dashboard' ? $query->orderByDesc('o.ordered_on')->orderByDesc('o.id')->limit(5)->get() : $query->orderByDesc('o.id')->paginate(25)), 'products' => DB::table('products')->orderBy('name')->get(), 'batches' => $reports->batches($from, $to), 'selectedOrder' => $selected, 'items' => $selected ? DB::table('order_items')->where('order_id', $selected->id)->get() : collect(), 'allocations' => $selected ? $ops->allocations($selected->id) : collect(), 'receipts' => $selected ? DB::table('receipts')->where('order_id', $selected->id)->orderBy('id')->get() : collect(), 'netPaid' => $selected ? $ops->paid($selected->id) : 0, 'issued' => $selected ? DB::table('invoices')->where('order_id', $selected->id)->orderByDesc('version')->get() : collect(), 'bars' => $bars, 'statuses' => DB::table('orders')->whereBetween('ordered_on', [$from, $to])->select('status')->selectRaw('COUNT(*) count')->groupBy('status')->pluck('count', 'status'), 'dispatchCount' => DB::table('orders')->where('status', 'Pending')->count()])->layout('layouts.app', ['page' => $this->page]);
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
