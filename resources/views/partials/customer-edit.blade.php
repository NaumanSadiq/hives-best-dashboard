<form wire:submit="saveCustomer"><p>Changes supply the details for future orders. Existing order snapshots, receipts and issued invoices are unchanged.</p>
@foreach(['name'=>'Customer name','phone'=>'Phone'] as $field=>$label)<label class="form-label mt-2" for="profile-{{ $field }}">{{ $label }}</label><x-input id="profile-{{ $field }}" class="form-control" wire:model="profile.{{ $field }}" required />@endforeach
<label for="profile-address" class="form-label mt-3">Delivery address</label><x-textarea id="profile-address" class="form-control" wire:model="profile.address" required rows="3" />
<label class="form-check mt-3"><input class="form-check-input" type="checkbox" wire:model="reviewedCustomer"><span class="form-check-label">I have checked this customer’s identity and any shared or missing phone information.</span></label>
<button class="btn btn-peach mt-3" wire:loading.attr="disabled">Save customer profile</button></form>
