@extends('admin.layouts.app')
@section('content')
    <div class="admin-heading"><div><span>Security</span><h1>Create staff account</h1></div></div>
    <form class="admin-form" method="POST" action="{{ route('azari.admin.staff.store') }}" enctype="multipart/form-data">
        @csrf
        <label>Full name<input name="name" value="{{ old('name') }}" required></label>
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>Role
            <select name="staff_role" required>
                <option value="support">Support</option>
                <option value="administrator">Administrator</option>
            </select>
        </label>
        <label>Profile picture<input type="file" name="profile_photo" accept="image/*"></label>
        <label>Password
            <div class="admin-password-row">
                <input id="staff-password" type="text" name="password" minlength="12" required>
                <button type="button" class="button button-secondary" data-generate-password>Generate</button>
            </div>
        </label>
        <label>Confirm password<input id="staff-password-confirmation" type="text" name="password_confirmation" minlength="12" required></label>
        <button class="button button-primary" type="submit">Create staff account</button>
    </form>
@endsection
