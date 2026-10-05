<div class="reserva-commercial-grid">
    <label>Name<input name="name" value="{{ old('name', $type->name) }}" required></label>
    <label>Slug<input name="slug" value="{{ old('slug', $type->slug) }}"></label>
    <label>Category
        <select name="room_type_id">
            <option value="">Use property category</option>
            @foreach($roomTypes as $roomType)
                <option value="{{ $roomType->id }}" @selected((string) old('room_type_id', $type->room_type_id) === (string) $roomType->id)>{{ $roomType->name }}</option>
            @endforeach
        </select>
    </label>
    <label>Total inventory<input name="total_inventory" type="number" min="1" max="10000" value="{{ old('total_inventory', $type->total_inventory ?: 1) }}" required></label>
    <label>Adult capacity / unit<input name="adult_capacity" type="number" min="1" max="100" value="{{ old('adult_capacity', $type->adult_capacity ?: 2) }}" required></label>
    <label>Child capacity / unit<input name="child_capacity" type="number" min="0" max="100" value="{{ old('child_capacity', $type->child_capacity ?? 0) }}" required></label>
    <label>Maximum guests / unit<input name="max_guests" type="number" min="1" max="100" value="{{ old('max_guests', $type->max_guests ?: 2) }}" required></label>
    <label>Bedrooms<input name="bedrooms" type="number" min="0" max="30" value="{{ old('bedrooms', $type->bedrooms ?? 1) }}" required></label>
    <label>Bathrooms<input name="bathrooms" type="number" min="0" max="30" value="{{ old('bathrooms', $type->bathrooms ?? 1) }}" required></label>
    <label>Bed configuration<input name="bed_configuration" value="{{ old('bed_configuration', $type->bed_configuration) }}"></label>
    <label>Room size<input name="room_size" type="number" min="0" step="0.01" value="{{ old('room_size', $type->room_size) }}"></label>
    <label>Base rate ({{ $currency }})<input name="base_rate" type="number" min="0" step="0.01" value="{{ old('base_rate', $type->base_rate ?? 0) }}" required></label>
    <label>Weekend rate ({{ $currency }})<input name="weekend_rate" type="number" min="0" step="0.01" value="{{ old('weekend_rate', $type->weekend_rate) }}"></label>
    <label>Cleaning fee<input name="cleaning_fee" type="number" min="0" step="0.01" value="{{ old('cleaning_fee', $type->cleaning_fee ?? 0) }}"></label>
    <label>Service charge<input name="service_charge" type="number" min="0" step="0.01" value="{{ old('service_charge', $type->service_charge ?? 0) }}"></label>
    <label>Security deposit<input name="security_deposit" type="number" min="0" step="0.01" value="{{ old('security_deposit', $type->security_deposit ?? 0) }}"></label>
    <label>Tax rate (%)<input name="tax_rate" type="number" min="0" max="100" step="0.001" value="{{ old('tax_rate', $type->tax_rate ?? 0) }}"></label>
    <label>Minimum stay<input name="minimum_stay" type="number" min="1" max="730" value="{{ old('minimum_stay', $type->minimum_stay ?: 1) }}"></label>
    <label>Maximum stay<input name="maximum_stay" type="number" min="1" max="730" value="{{ old('maximum_stay', $type->maximum_stay) }}"></label>
    <label>Sort order<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $type->sort_order ?? 0) }}"></label>
    <label class="reserva-commercial-span-2">Description<textarea name="description">{{ old('description', $type->description) }}</textarea></label>
</div>
<div class="reserva-commercial-checks">
    <label><input type="checkbox" name="same_day_booking" value="1" @checked(old('same_day_booking', $type->same_day_booking))> Same-day booking</label>
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->exists ? $type->is_active : true))> Active</label>
    <label><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $type->exists ? $type->is_published : true))> Public</label>
</div>
