<div class="grid gap-4 sm:grid-cols-2">
<x-form-field name="title" :label="__('phase10.title')" :value="old('title', $dossier?->title)" required maxlength="200" />
<x-form-field name="permit_type" :label="__('phase10.type')" :value="old('permit_type', $dossier?->permit_type)" required maxlength="120" />
<x-form-field name="municipality" :label="__('phase10.municipality')" :value="old('municipality', $dossier?->municipality)" maxlength="150" />
<x-form-field name="status" :label="__('phase10.status')" :value="old('status', $dossier?->status)" required maxlength="120" />
<x-form-field name="submitted_on" type="date" :label="__('phase10.submitted')" :value="old('submitted_on', $dossier?->submitted_on?->toDateString())" />
<x-form-field name="due_on" type="date" :label="__('phase10.due')" :value="old('due_on', $dossier?->due_on?->toDateString())" />
<label class="block sm:col-span-2" for="permit-observations"><span class="billing-label">{{ __('phase10.observations') }}</span><textarea id="permit-observations" name="observations" maxlength="5000" rows="3" class="w-full">{{ old('observations', $dossier?->observations) }}</textarea></label>
</div>
