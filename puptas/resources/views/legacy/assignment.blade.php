<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="{{ asset('assets/maintenance/assignee/assignment.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
@if (session('success'))
    <div id="snackbar" class="snackbar show">{{ session('success') }}</div>
@endif

<form action="{{ route('admin.users.store') }}" method="POST" class="assign-form">
    @csrf
    <!-- Salutation -->
    <div>
    <h1>Create User and Assign to Programs</h1>
        <label for="salutation">Salutation:</label>
        <select id="salutation" name="salutation" required> 
            <option value="" disabled selected>---- Select Salutation ----</option>
            <option value="Mr.">Mr.</option>
            <option value="Ms.">Ms.</option>
            <option value="Mrs.">Mrs.</option>
            <option value="Sr.">Sr.</option>
            <option value="Mx.">Mx.</option>
            <option value="Prof.">Prof.</option>
            <option value="Dr.">Dr.</option>
        </select>
        @error('salutation')
            <div class="text-danger">{{ $message }}</div>
        @enderror
    </div>

    <!-- Two-column form fields for Last Name, First Name, Phone, Email -->
    <div class="form-container">
        <!-- Last Name -->
        <div class="form-group">
            <label for="lastname">Last Name:</label>
            <input type="text" name="lastname" value="{{ old('lastname') }}" required>
            @error('lastname')
                <div class="text-danger">{{ $message }}</div>
            @enderror
        </div>

        <!-- First Name -->
        <div class="form-group">
            <label for="firstname">First Name:</label>
            <input type="text" name="firstname" value="{{ old('firstname') }}" required>
            @error('firstname')
                <div class="text-danger">{{ $message }}</div>
            @enderror
        </div>

        <!-- Contact Number -->
        <div class="form-group">
            <label for="contactnumber">Phone:</label>
            <input type="text" name="contactnumber" value="{{ old('contactnumber') }}" required>
            @error('contactnumber')
                <div class="text-danger">{{ $message }}</div>
            @enderror
        </div>

        <!-- Email -->
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" name="email" value="{{ old('email') }}" required>
            @error('email')
                <div class="text-danger">{{ $message }}</div>
            @enderror
        </div>
    </div>


    <!-- Role Selection -->
    <div>
        <label for="role">Role:</label>
        <select name="role" required>
            <option value="">Select Role</option>
            <option value="3" {{ old('role') == 3 ? 'selected' : '' }}>Evaluator</option>
            <option value="4" {{ old('role') == 4 ? 'selected' : '' }}>Interviewer</option>
        </select>
        @error('role')
            <div class="text-danger">{{ $message }}</div>
        @enderror
    </div>

    <!-- Program Assignment -->
    <div>
        <label for="programs">Assign to Programs:</label>
        <select name="programs[]" multiple required>
            @foreach($programs as $program)
                <option value="{{ $program->id }}" {{ (collect(old('programs'))->contains($program->id)) ? 'selected':'' }}>
                    {{ $program->name }}
                </option>
            @endforeach
        </select>
        @error('programs')
            <div class="text-danger">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <button type="submit"> <i class="fa-solid fa-user-plus"></i> CREATE</button>
    </div>
</form>

<h2>Assigned Evaluators and Interviewers</h2>
@if ($assignedUsers->isEmpty())
    <p>No evaluators or interviewers assigned yet.</p>
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
                    <td>
                        @if ($user->role_id == 3)
                            Evaluator
                        @elseif ($user->role_id == 4)
                            Interviewer
                        @endif
                    </td>
                    <td>
                        @if ($user->programs->isEmpty())
                            Not assigned to any program
                        @else
                            @foreach ($user->programs as $program)
                                {{ $program->name }}@if (!$loop->last), @endif
                            @endforeach
                        @endif
                    </td>
                    <td>
                        <div class="action-container">
                            <!-- Edit Button -->
                            <a href="{{ route('admin.users.edit', $user->id) }}" class="action-button edit-button">
                                <i class="fas fa-edit"></i>
                                <span class="tooltiptext">Edit</span> <!-- Tooltip text -->
                            </a>
                        </div>
                            <!-- Delete Button -->
                            <form action="{{ route('admin.users.delete', $user->id) }}" method="POST" class="action-button delete-button">
                                @csrf
                                @method('DELETE')
                            <div class="action-container2">
                                <button type="submit" class="action-button delete-button">
                                    <i class="fas fa-trash-alt"></i>
                                    <span class="tooltiptext">Delete</span> <!-- Tooltip text -->
                                </button>
                            </div>
                            </form>
                        </div>
                    </td>

                </tr>
            @endforeach
        </tbody>
    </table>
@endif
<!-- Delete Confirmation Dialog -->
<div id="deleteConfirmationDialog" class="modal">
    <div class="modal-content">
        <p>Are you sure you want to delete this Assignee?</p>
        <div class="modal-buttons">
            <button id="cancelButton" class="cancel-button">Cancel</button>
            <button id="confirmDeleteButton" class="delete-button">Delete</button>
        </div>
    </div>
</div>
</html>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const deleteButtons = document.querySelectorAll(".action-button.delete-button");
    const deleteDialog = document.getElementById("deleteConfirmationDialog");
    const cancelButton = document.getElementById("cancelButton");
    const confirmDeleteButton = document.getElementById("confirmDeleteButton");

    let deleteForm = null;

    deleteButtons.forEach(button => {
        button.addEventListener("click", function (event) {
            event.preventDefault();
            deleteForm = button.closest("form");
            deleteDialog.style.display = "block";
        });
    });

    cancelButton.addEventListener("click", function () {
        deleteDialog.style.display = "none";
    });

    confirmDeleteButton.addEventListener("click", function () {
        if (deleteForm) {
            deleteDialog.style.display = "none";
            setTimeout(() => {
                deleteForm.submit();
                showSnackbar("User deleted successfully", "delete-snackbar");
            }, 500);
        }
    });

    const addSnackbar = document.getElementById("snackbar");
    if (addSnackbar) {
        addSnackbar.classList.add("add-snackbar");
        setTimeout(() => {
            addSnackbar.classList.remove("show");
        }, 3000);
    }
});

function showSnackbar(message, type = "general-snackbar") {
    const snackbar = document.createElement("div");
    snackbar.className = `snackbar show ${type}`;
    snackbar.textContent = message;
    document.body.appendChild(snackbar);
    setTimeout(() => {
        snackbar.classList.remove("show");
        snackbar.remove();
    }, 3000);
}
</script>
