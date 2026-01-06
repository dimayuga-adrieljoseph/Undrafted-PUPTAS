<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="{{ asset('assets/maintenance/assignee/edit.css') }}">
</head>

@if (session('error'))
<div class="error-message">
    {{ session('error') }}
</div>
@endif

@if ($errors->any())
<div class="error-message">
    <ul>
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<h1>Edit Assigned User</h1>

<form action="{{ route('admin.users.update', $user->id) }}" method="POST">
    @csrf
    <!-- Salutation -->
    <div>
        <label for="salutation">Salutation:</label>
        <select id="salutation" name="salutation" required>
            <option value="Mr." {{ $user->salutation == 'Mr.' ? 'selected' : '' }}>Mr.</option>
            <option value="Ms." {{ $user->salutation == 'Ms.' ? 'selected' : '' }}>Ms.</option>
            <option value="Mrs." {{ $user->salutation == 'Mrs.' ? 'selected' : '' }}>Mrs.</option>
            <option value="Sr." {{ $user->salutation == 'Sr.' ? 'selected' : '' }}>Sr.</option>
            <option value="Mx." {{ $user->salutation == 'Mx.' ? 'selected' : '' }}>Mx.</option>
            <option value="Prof." {{ $user->salutation == 'Prof.' ? 'selected' : '' }}>Prof.</option>
            <option value="Dr." {{ $user->salutation == 'Dr.' ? 'selected' : '' }}>Dr.</option>
        </select>
    </div>

    <!-- Last Name -->
    <div>
        <label for="lastname">Last Name:</label>
        <input type="text" name="lastname" value="{{ $user->lastname }}" required>
    </div>

    <!-- First Name -->
    <div>
        <label for="firstname">First Name:</label>
        <input type="text" name="firstname" value="{{ $user->firstname }}" required>
    </div>

    <!-- Contact Number -->
    <div>
        <label for="contactnumber">Phone:</label>
        <input type="text" name="contactnumber" value="{{ $user->contactnumber }}" required>
    </div>

    <!-- Email -->
    <div>
        <label for="email">Email:</label>
        <input type="email" name="email" value="{{ $user->email }}" required>
    </div>

    <!-- Role Selection -->
    <div>
        <label for="role">Role:</label>
        <select name="role" required>
            <option value="3" {{ $user->role_id == 3 ? 'selected' : '' }}>Evaluator</option>
            <option value="4" {{ $user->role_id == 4 ? 'selected' : '' }}>Interviewer</option>
        </select>
    </div>

    <!-- Program Assignment -->
    <div>
        <label for="programs">Assign to Programs:</label>
        <small style="display: block; color: #666; margin-bottom: 5px; font-style: italic;">Hold Ctrl (Windows) or Cmd (Mac) to select multiple programs</small>
        <select name="programs[]" multiple required size="5" style="min-height: 120px;">
            @foreach($programs as $program)
            <option value="{{ $program->id }}" {{ in_array($program->id, $assignedPrograms) ? 'selected' : '' }}>
                {{ $program->name }}
            </option>
            @endforeach
        </select>
    </div>

    <!-- Submit Button -->
    <div>
        <button type="submit">Update User</button>
    </div>
</form>

</html>