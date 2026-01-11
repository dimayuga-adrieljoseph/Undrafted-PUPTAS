<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assigned User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Base Styles */
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 2rem;
            color: #333;
        }

        h1 {
            text-align: center;
            color: #9E122C;
            margin-bottom: 2rem;
        }

        /* Card Form */
        .form-card {
            background: #fff;
            max-width: 700px;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

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
        select {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1rem;
            transition: border 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        select:focus {
            outline: none;
            border-color: #9E122C;
            box-shadow: 0 0 0 2px rgba(158, 18, 44, 0.2);
        }

        select[multiple] {
            min-height: 120px;
        }

        small {
            font-size: 0.85rem;
            color: #666;
            display: block;
            margin-bottom: 0.5rem;
        }

        /* Buttons */
        button {
            background: #9E122C;
            color: #fff;
            padding: 0.65rem 1.25rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: background 0.3s;
        }

        button:hover {
            background: #7a0f24;
        }

        /* Error Messages */
        .error-message {
            max-width: 700px;
            margin: 0 auto 1rem auto;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            font-size: 0.95rem;
        }

        .error-message ul {
            padding-left: 1.25rem;
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .form-card {
                padding: 1rem;
            }
        }
    </style>
</head>

<body>

    <!-- Error Messages -->
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

    <div class="form-card">
        <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
            @csrf

            <!-- Salutation -->
            <div class="form-group">
                <label for="salutation">Salutation</label>
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
            <div class="form-group">
                <label for="lastname">Last Name</label>
                <input type="text" name="lastname" value="{{ $user->lastname }}" required>
            </div>

            <!-- First Name -->
            <div class="form-group">
                <label for="firstname">First Name</label>
                <input type="text" name="firstname" value="{{ $user->firstname }}" required>
            </div>

            <!-- Contact Number -->
            <div class="form-group">
                <label for="contactnumber">Phone</label>
                <input type="text" name="contactnumber" value="{{ $user->contactnumber }}" required>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" value="{{ $user->email }}" required>
            </div>

            <!-- Role Selection -->
            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" required>
                    <option value="3" {{ $user->role_id == 3 ? 'selected' : '' }}>Evaluator</option>
                    <option value="4" {{ $user->role_id == 4 ? 'selected' : '' }}>Interviewer</option>
                </select>
            </div>

            <!-- Program Assignment -->
            <div class="form-group">
                <label for="programs">Assign to Programs</label>
                <small>Hold Ctrl (Windows) or Cmd (Mac) to select multiple programs</small>
                <select name="programs[]" multiple required>
                    @foreach($programs as $program)
                    <option value="{{ $program->id }}" {{ in_array($program->id, $assignedPrograms) ? 'selected' : '' }}>
                        {{ $program->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- Submit Button -->
            <div class="form-group">
                <button type="submit"><i class="fas fa-save"></i> Update User</button>
            </div>
        </form>
    </div>

</body>

</html>
