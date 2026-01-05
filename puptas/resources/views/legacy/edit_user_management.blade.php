<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="{{ asset('assets/maintenance/user_accounts/add.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .back-button {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 1rem 0;
            transition: background 0.3s;
        }

        .back-button:hover {
            background: #5a6268;
        }

        .page-header {
            max-width: 800px;
            margin: 2rem auto 0;
            padding: 0 2rem;
        }
    </style>
</head>

<body>
    <div class="page-header">
        <a href="{{ route('users.index') }}" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Manage Users
        </a>
    </div>

    <div class="flex flex-col min-h-screen">
        <div class="form-wrapper">
            <div class="form-box">
                <h2 class="form-title">Edit User</h2>

                @if($errors->any())
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                    <ul style="margin: 0; padding-left: 1.5rem;">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form method="POST" action="{{ route('users.update', $user->id) }}">
                    @csrf

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="firstname">First Name <span class="required">*</span></label>
                            <input id="firstname" class="form-input" type="text" name="firstname"
                                value="{{ old('firstname', $user->firstname) }}" required autocomplete="firstname" />
                            <span class="form-error" id="firstname-error"></span>
                        </div>

                        <div class="form-group half-width">
                            <label for="lastname">Last Name <span class="required">*</span></label>
                            <input id="lastname" class="form-input" type="text" name="lastname"
                                value="{{ old('lastname', $user->lastname) }}" required autocomplete="lastname" />
                            <span class="form-error" id="lastname-error"></span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="middlename">Middle Name</label>
                            <input id="middlename" class="form-input" type="text" name="middlename"
                                value="{{ old('middlename', $user->middlename) }}" autocomplete="middlename" />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="email">Email <span class="required">*</span></label>
                            <input id="email" class="form-input" type="email" name="email"
                                value="{{ old('email', $user->email) }}" required autocomplete="email"
                                pattern="[a-z0-9._%+\-]+@gmail\.com$"
                                title="Must be a valid email format like example@gmail.com" />
                            <span class="form-error" id="email-error"></span>
                        </div>

                        <div class="form-group half-width">
                            <label for="contactnumber">Contact Number <span class="required">*</span></label>
                            <div class="form-input-group">
                                <div class="input-prefix">+63</div>
                                <input id="contactnumber" class="form-input" type="text" name="contactnumber"
                                    value="{{ old('contactnumber', $user->contactnumber) }}" required autocomplete="contactnumber"
                                    maxlength="10" pattern="\d{10}"
                                    title="Invalid contact number. Must be exactly 10 digits." />
                            </div>
                            <span class="form-error" id="contactnumber-error"></span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="password">New Password (leave blank to keep current)</label>
                            <input id="password" class="form-input" type="password" name="password"
                                autocomplete="new-password"
                                pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{8,}" />
                            <span class="form-error" id="password-error"></span>
                            <small style="color: #666; display: block; margin-top: 5px;">
                                <strong>Password Requirements:</strong><br>
                                • At least 8 characters<br>
                                • At least one special characters<br>
                                • At least one uppercase letter (A-Z)<br>
                                • At least one lowercase letter (a-z)<br>
                                • At least one number (0-9)<br>
                            </small>
                        </div>

                        <div class="form-group half-width">
                            <label for="password_confirmation">Confirm New Password</label>
                            <input id="password_confirmation" class="form-input" type="password"
                                name="password_confirmation" autocomplete="new-password" />
                            <span class="form-error" id="password_confirmation-error"></span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="role_id">Select User Type <span class="required">*</span></label>
                            <select id="role_id" name="role_id" class="form-select" required>
                                @foreach($roles as $roleId => $roleName)
                                <option value="{{ $roleId }}" {{ old('role_id', $user->role_id) == $roleId ? 'selected' : '' }}>
                                    {{ $roleName }}
                                </option>
                                @endforeach
                            </select>
                            <span class="form-error" id="role_id-error"></span>
                        </div>
                    </div>

                    <div class="form-row" id="program-group" style="{{ old('role_id', $user->role_id) == 1 ? '' : 'display: none;' }}">
                        <div class="form-group full-width">
                            <label for="program">Program <span class="required">*</span></label>
                            <select id="program" name="program" class="form-select">
                                <option value="" disabled>---- Select Program ----</option>
                                @foreach($programs as $program)
                                <option value="{{ $program->id }}"
                                    {{ $user->programs->contains($program->id) ? 'selected' : '' }}>
                                    {{ $program->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group mt-4 flex items-center justify-end">
                        <a href="{{ route('users.index') }}" class="form-button ms-4"
                            style="background: #6c757d; text-decoration: none; display: inline-block;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="form-button ms-4">
                            <i class="fas fa-save"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Show/hide program dropdown based on role selection
        document.getElementById('role_id').addEventListener('change', function() {
            const programGroup = document.getElementById('program-group');
            const programSelect = document.getElementById('program');

            if (this.value == '1') { // Applicant role
                programGroup.style.display = '';
                programSelect.required = true;
            } else {
                programGroup.style.display = 'none';
                programSelect.required = false;
            }
        });
    </script>
</body>

</html>