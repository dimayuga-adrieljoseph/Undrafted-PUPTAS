<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create & Assign Users</title>
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
            text-align: center;
            color: #9E122C;
            margin-bottom: 1.5rem;
        }

        /* Form Card */
        .form-card {
            max-width: 900px;
            margin: 0 auto 2rem auto;
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        .form-group {
            margin-bottom: 1.25rem;
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
            transition: border 0.3s, box-shadow 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        select:focus {
            outline: none;
            border-color: #9E122C;
            box-shadow: 0 0 0 2px rgba(158, 18, 44, 0.2);
        }

        /* Two-column layout */
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .half-width {
            width: 48%;
        }

        /* Submit Button */
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

        /* Success & Error Snackbars */
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

        /* Assigned Users Table */
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-top: 2rem;
        }

        th, td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e0e0e0;
            text-align: left;
        }

        th {
            background: #f5f5f5;
            font-weight: 600;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        .action-container {
            display: flex;
            gap: 0.5rem;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
            position: relative;
        }

        .edit-button {
            background: #2196f3;
            color: #fff;
        }

        .edit-button:hover {
            background: #1976d2;
        }

        .delete-button {
            background: #f44336;
            color: #fff;
        }

        .delete-button:hover {
            background: #d32f2f;
        }

        .tooltiptext {
            visibility: hidden;
            width: 80px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 4px;
            padding: 3px 0;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.75rem;
        }

        .action-button:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Delete Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .modal-buttons {
            margin-top: 1.5rem;
            display: flex;
            justify-content: space-around;
        }

        .modal-buttons button {
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-buttons .cancel-button {
            background: #6c757d;
            color: #fff;
        }

        .modal-buttons .cancel-button:hover {
            background: #5a6268;
        }

        .modal-buttons .delete-button {
            background: #f44336;
            color: #fff;
        }

        .modal-buttons .delete-button:hover {
            background: #d32f2f;
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

    <!-- Form Card -->
    <div class="form-card">
        <h1>Create User & Assign Programs</h1>

        <!-- Error Messages -->
        @if ($errors->any())
        <div class="error-message">
            <ul>
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('admin.users.store') }}" method="POST" id="createUserForm">
            @csrf

            <!-- Salutation -->
            <div class="form-group">
                <label for="salutation">Salutation <span class="required">*</span></label>
                <select name="salutation" id="salutation" required>
                    <option value="" disabled selected>Select Salutation</option>
                    <option value="Mr.">Mr.</option>
                    <option value="Ms.">Ms.</option>
                    <option value="Mrs.">Mrs.</option>
                    <option value="Sr.">Sr.</option>
                    <option value="Mx.">Mx.</option>
                    <option value="Prof.">Prof.</option>
                    <option value="Dr.">Dr.</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="lastname">Last Name <span class="required">*</span></label>
                    <input type="text" name="lastname" value="{{ old('lastname') }}" required>
                </div>

                <div class="form-group half-width">
                    <label for="firstname">First Name <span class="required">*</span></label>
                    <input type="text" name="firstname" value="{{ old('firstname') }}" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="contactnumber">Phone <span class="required">*</span></label>
                    <input type="text" name="contactnumber" value="{{ old('contactnumber') }}" required>
                </div>

                <div class="form-group half-width">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                </div>
            </div>

            <div class="form-group">
                <label for="role">Role <span class="required">*</span></label>
                <select name="role" required>
                    <option value="" disabled>Select Role</option>
                    <option value="3" {{ old('role') == 3 ? 'selected':'' }}>Evaluator</option>
                    <option value="4" {{ old('role') == 4 ? 'selected':'' }}>Interviewer</option>
                </select>
            </div>

            <div class="form-group">
                <label for="programs">Assign to Programs <span class="required">*</span></label>
                <small>Hold Ctrl (Windows) or Cmd (Mac) to select multiple programs</small>
                <select name="programs[]" multiple required size="5">
                    @foreach($programs as $program)
                    <option value="{{ $program->id }}" {{ (collect(old('programs'))->contains($program->id)) ? 'selected':'' }}>
                        {{ $program->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div style="text-align: right; margin-top: 1.5rem;">
                <button type="submit" class="form-button"><i class="fas fa-user-plus"></i> CREATE</button>
            </div>
        </form>
    </div>

    <!-- Assigned Users Table -->
    <div class="form-card">
        <h2>Assigned Evaluators & Interviewers</h2>
        @if ($assignedUsers->isEmpty())
        <p style="text-align:center; color:#666;">No evaluators or interviewers assigned yet.</p>
        @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Assigned Programs</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assignedUsers as $user)
                <tr>
                    <td>{{ $user->salutation }} {{ $user->firstname }} {{ $user->lastname }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->role_id == 3 ? 'Evaluator' : 'Interviewer' }}</td>
                    <td>
                        @if ($user->programs->isEmpty())
                        Not assigned
                        @else
                        {{ $user->programs->pluck('name')->join(', ') }}
                        @endif
                    </td>
                    <td>
                        <div class="action-container">
                            <a href="{{ route('admin.users.edit', $user->id) }}" class="action-button edit-button">
                                <i class="fas fa-edit"></i>
                                <span class="tooltiptext">Edit</span>
                            </a>
                            <form action="{{ route('admin.users.delete', $user->id) }}" method="POST" class="delete-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="action-button delete-button">
                                    <i class="fas fa-trash-alt"></i>
                                    <span class="tooltiptext">Delete</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <p>Are you sure you want to delete this user?</p>
            <div class="modal-buttons">
                <button id="cancelDelete" class="cancel-button">Cancel</button>
                <button id="confirmDelete" class="delete-button">Delete</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const deleteForms = document.querySelectorAll(".delete-form");
            const modal = document.getElementById("deleteModal");
            const cancelBtn = document.getElementById("cancelDelete");
            const confirmBtn = document.getElementById("confirmDelete");
            let activeForm = null;

            deleteForms.forEach(form => {
                form.querySelector("button").addEventListener("click", function (e) {
                    e.preventDefault();
                    activeForm = form;
                    modal.style.display = "flex";
                });
            });

            cancelBtn.addEventListener("click", () => {
                modal.style.display = "none";
            });

            confirmBtn.addEventListener("click", () => {
                if (activeForm) {
                    activeForm.submit();
                }
            });

            // Snackbar auto-hide
            ["snackbar", "error-snackbar"].forEach(id => {
                const sb = document.getElementById(id);
                if (sb) {
                    setTimeout(() => sb.classList.remove("show"), 3000);
                }
            });
        });
    </script>

</body>

</html>
