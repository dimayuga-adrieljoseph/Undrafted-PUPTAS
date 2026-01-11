<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 2rem;
            color: #333;
        }

        h1, h2 {
            color: #9E122C;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        /* User Types Dashboard */
        .user-types-section {
            max-width: 1000px;
            margin: 0 auto 2rem;
        }

        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        }

        .user-type-info {
            background: #fff;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
        }

        .user-type-icon {
            font-size: 2rem;
            color: #9E122C;
            margin-bottom: 0.5rem;
        }

        .user-type-text {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .user-type-count {
            font-size: 1.5rem;
            font-weight: bold;
        }

        /* Form Card */
        .form-card {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .half-width {
            width: 48%;
        }

        .full-width {
            width: 100%;
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

        input:focus,
        select:focus {
            outline: none;
            border-color: #9E122C;
            box-shadow: 0 0 0 2px rgba(158, 18, 44, 0.2);
        }

        .form-input-group {
            display: flex;
        }

        .input-prefix {
            padding: 0.65rem 0.75rem;
            background: #eee;
            border-radius: 8px 0 0 8px;
            border: 1px solid #ccc;
            border-right: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-input-group input {
            border-radius: 0 8px 8px 0;
            border: 1px solid #ccc;
            flex: 1;
        }

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

        /* Snackbars */
        .snackbar {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s ease;
            z-index: 1000;
        }

        .snackbar.show {
            opacity: 1;
            visibility: visible;
        }

        .success-snackbar {
            background: #28a745;
        }

        .error-snackbar {
            background: #dc3545;
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

    <!-- User Types Dashboard -->
    <div class="user-types-section">
        <h2>User Types</h2>
        <div class="grid">
            <div class="user-type-info">
                <div class="user-type-icon"><i class="fas fa-users"></i></div>
                <div class="user-type-text">Total Users</div>
                <div class="user-type-count">{{ $totalUsers }}</div>
            </div>
            <div class="user-type-info">
                <div class="user-type-icon"><i class="fas fa-user"></i></div>
                <div class="user-type-text">Applicants</div>
                <div class="user-type-count">{{ $userCountsByRole[1] ?? 0 }}</div>
            </div>
            <div class="user-type-info">
                <div class="user-type-icon"><i class="fas fa-tools"></i></div>
                <div class="user-type-text">Admins</div>
                <div class="user-type-count">{{ $userCountsByRole[2] ?? 0 }}</div>
            </div>
            <div class="user-type-info">
                <div class="user-type-icon"><i class="fas fa-check"></i></div>
                <div class="user-type-text">Evaluator</div>
                <div class="user-type-count">{{ $userCountsByRole[3] ?? 0 }}</div>
            </div>
            <div class="user-type-info">
                <div class="user-type-icon"><i class="fas fa-edit"></i></div>
                <div class="user-type-text">Interviewer</div>
                <div class="user-type-count">{{ $userCountsByRole[4] ?? 0 }}</div>
            </div>
            <div class="user-type-info">
                <div class="user-type-icon"><i class="fa-solid fa-suitcase-medical"></i></div>
                <div class="user-type-text">Medical Staff</div>
                <div class="user-type-count">{{ $userCountsByRole[5] ?? 0 }}</div>
            </div>
            <div class="user-type-info">
                <div class="user-type-icon"><i class="fas fa-user"></i></div>
                <div class="user-type-text">Registrar</div>
                <div class="user-type-count">{{ $userCountsByRole[6] ?? 0 }}</div>
            </div>
        </div>
    </div>

    <!-- Add User Form -->
    <div class="form-card">
        <h1>Add New User</h1>
        <form id="addUserForm" method="POST" action="{{ route('add_user.store') }}">
            @csrf
            <div class="form-row">
                <div class="form-group half-width">
                    <label for="firstname">First Name *</label>
                    <input id="firstname" type="text" name="firstname" required>
                </div>
                <div class="form-group half-width">
                    <label for="lastname">Last Name *</label>
                    <input id="lastname" type="text" name="lastname" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="middlename">Middle Name</label>
                    <input id="middlename" type="text" name="middlename">
                </div>
                <div class="form-group half-width">
                    <label for="extension_name">Extension Name</label>
                    <input id="extension_name" type="text" name="extension_name">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="email">Email *</label>
                    <input id="email" type="email" name="email" required>
                </div>
                <div class="form-group half-width">
                    <label for="contactnumber">Contact Number *</label>
                    <div class="form-input-group">
                        <div class="input-prefix">+63</div>
                        <input id="contactnumber" type="text" name="contactnumber" maxlength="10" required>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="password">Password *</label>
                    <input id="password" type="password" name="password" required>
                </div>
                <div class="form-group half-width">
                    <label for="password_confirmation">Confirm Password *</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width">
                    <label for="role_id">User Type *</label>
                    <select id="role_id" name="role_id" required>
                        <option value="" disabled selected>Select User Type</option>
                        <option value="1">Applicant</option>
                        <option value="2">Admin</option>
                        <option value="3">Evaluator</option>
                        <option value="4">Interviewer</option>
                        <option value="5">Medical Staff</option>
                        <option value="6">Registrar</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width" id="program-group">
                    <label for="program">Program *</label>
                    <select id="program" name="program">
                        <option value="" disabled selected>Select Program</option>
                        @foreach($programs as $program)
                        <option value="{{ $program->id }}">{{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="text-align: right; margin-top: 1.5rem;">
                <button type="submit" class="form-button"><i class="fas fa-user-plus"></i> Add User</button>
            </div>
        </form>
    </div>

    <!-- Snackbar -->
    <div id="snackbar" class="snackbar error-snackbar">Email is already used.</div>
    <div id="snackbar-password" class="snackbar error-snackbar">Password didn't match.</div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const roleSelect = document.getElementById("role_id");
            const programGroup = document.getElementById("program-group");

            roleSelect.addEventListener("change", () => {
                programGroup.style.display = roleSelect.value == "1" ? "block" : "none";
            });
        });

        function showSnackbar(id) {
            const sb = document.getElementById(id);
            sb.classList.add("show");
            setTimeout(() => sb.classList.remove("show"), 3000);
        }
    </script>

</body>

</html>
