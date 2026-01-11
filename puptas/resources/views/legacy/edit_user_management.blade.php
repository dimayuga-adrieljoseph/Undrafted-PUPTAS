<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 2rem;
            color: #333;
        }

        a {
            text-decoration: none;
        }

        h2 {
            color: #9E122C;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        /* Form Card */
        .form-card {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1rem;
            transition: border 0.3s, box-shadow 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #9E122C;
            box-shadow: 0 0 0 2px rgba(158, 18, 44, 0.2);
        }

        /* Input prefix for phone */
        .form-input-group {
            display: flex;
            align-items: center;
        }

        .input-prefix {
            padding: 0.65rem 0.75rem;
            background: #e9ecef;
            border: 1px solid #ccc;
            border-right: none;
            border-radius: 8px 0 0 8px;
            font-weight: 500;
        }

        .form-input-group input {
            border-radius: 0 8px 8px 0;
            border-left: none;
            flex: 1;
        }

        /* Half-width fields */
        .half-width {
            width: 48%;
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Buttons */
        .form-button {
            background: #9E122C;
            color: #fff;
            padding: 0.65rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.3s;
        }

        .form-button:hover {
            background: #7a0f24;
        }

        .form-button.cancel {
            background: #6c757d;
        }

        .form-button.cancel:hover {
            background: #5a6268;
        }

        /* Error Messages */
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .error-message ul {
            padding-left: 1.5rem;
            margin: 0;
        }

        /* Small notes */
        small {
            color: #666;
            display: block;
            margin-top: 0.25rem;
            font-size: 0.85rem;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .half-width {
                width: 100%;
            }

            .form-row {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

    <div class="form-card">
        <a href="{{ route('users.index') }}" class="form-button cancel" style="margin-bottom: 1rem;">
            <i class="fas fa-arrow-left"></i> Back
        </a>

        <h2>Edit User</h2>

        <!-- Error Messages -->
        @if($errors->any())
        <div class="error-message">
            <ul>
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
                    <input type="text" name="firstname" id="firstname" value="{{ old('firstname', $user->firstname) }}" required>
                </div>

                <div class="form-group half-width">
                    <label for="lastname">Last Name <span class="required">*</span></label>
                    <input type="text" name="lastname" id="lastname" value="{{ old('lastname', $user->lastname) }}" required>
                </div>
            </div>

            <div class="form-group">
                <label for="middlename">Middle Name</label>
                <input type="text" name="middlename" id="middlename" value="{{ old('middlename', $user->middlename) }}">
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" name="email" id="email"
                        value="{{ old('email', $user->email) }}" required
                        pattern="[a-z0-9._%+\-]+@gmail\.com$"
                        title="Must be a valid Gmail address like example@gmail.com">
                </div>

                <div class="form-group half-width">
                    <label for="contactnumber">Contact Number <span class="required">*</span></label>
                    <div class="form-input-group">
                        <div class="input-prefix">+63</div>
                        <input type="text" name="contactnumber" id="contactnumber"
                            value="{{ old('contactnumber', $user->contactnumber) }}" required maxlength="10"
                            pattern="\d{10}" title="Must be exactly 10 digits">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="password">New Password</label>
                    <input type="password" name="password" id="password"
                        pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&.]).{8,}">
                    <small>
                        At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
                    </small>
                </div>

                <div class="form-group half-width">
                    <label for="password_confirmation">Confirm Password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation">
                </div>
            </div>

            <div class="form-group">
                <label for="role_id">User Type <span class="required">*</span></label>
                <select name="role_id" id="role_id" required>
                    @foreach($roles as $roleId => $roleName)
                    <option value="{{ $roleId }}" {{ old('role_id', $user->role_id) == $roleId ? 'selected' : '' }}>
                        {{ $roleName }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" id="program-group" style="{{ old('role_id', $user->role_id) == 1 ? '' : 'display: none;' }}">
                <label for="program">Program <span class="required">*</span></label>
                <select id="program" name="program">
                    <option value="" disabled>---- Select Program ----</option>
                    @foreach($programs as $program)
                    <option value="{{ $program->id }}" {{ $user->programs->contains($program->id) ? 'selected' : '' }}>
                        {{ $program->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div style="text-align: right; margin-top: 1.5rem;">
                <a href="{{ route('users.index') }}" class="form-button cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="form-button">
                    <i class="fas fa-save"></i> Update User
                </button>
            </div>
        </form>
    </div>

    <script>
        // Show/hide program dropdown based on role selection
        document.getElementById('role_id').addEventListener('change', function() {
            const programGroup = document.getElementById('program-group');
            const programSelect = document.getElementById('program');

            if (this.value == '1') { // Applicant
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
