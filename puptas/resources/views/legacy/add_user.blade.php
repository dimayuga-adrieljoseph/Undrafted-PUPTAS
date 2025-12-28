
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="stylesheet" href="{{ asset('assets/maintenance/user_accounts/add.css') }}">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@material/dialog@14.0.0/dist/mdc.dialog.min.css">
    </head>
    <body>
        
    
    <div class="flex flex-col min-h-screen">
        <div class="user-types-section w-full max-w-4xl mx-auto p-8">
            <div class="text-center">
                <h2 class="text-2xl font-bold mb-4 text-maroon">User Types</h2>
                <div class="grid grid-cols-3 gap-4">
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
        </div>

        <div class="form-wrapper">
            <div class="form-box">
                <h2 class="form-title">Add New User</h2>
                <form id="addUserForm" method="POST" action="{{ route('add_user.store') }}">
                @csrf

                <!-- <div class="form-row">
                    <div class="form-group full-width">
                        <label for="salutation">Salutation <span class="required">*</span></label>
                        <select id="salutation" name="salutation" class="form-select" required>
                            <option value="" disabled selected>---- Select Salutation ----</option>
                            <option value="Mr.">Mr.</option>
                            <option value="Ms.">Ms.</option>
                            <option value="Mrs.">Mrs.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="Mx.">Mx.</option>
                            <option value="Prof.">Prof.</option>
                            <option value="Dr.">Dr.</option>
                        </select>
                        <span class="form-error" id="salutation-error"></span>
                    </div>
                </div> -->

                <div class="form-row">
                    <div class="form-group half-width">
                        <label for="firstname">First Name <span class="required">*</span></label>
                        <input id="firstname" class="form-input" type="text" name="firstname" required autocomplete="firstname" />
                        <span class="form-error" id="firstname-error"></span>
                    </div>

                    <div class="form-group half-width">
                        <label for="lastname">Last Name <span class="required">*</span></label>
                        <input id="lastname" class="form-input" type="text" name="lastname" required autocomplete="lastname" />
                        <span class="form-error" id="lastname-error"></span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group half-width">
                        <label for="middlename">Middle Name</label>
                        <input id="middlename" class="form-input" type="text" name="middlename" autocomplete="middlename" />
                    </div>

                    <div class="form-group half-width">
                        <label for="extension_name">Extension Name</label>
                        <input id="extension_name" class="form-input" type="text" name="extension_name" autocomplete="extension_name" />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group half-width">
                        <label for="email">Email <span class="required">*</span></label>
                        <input id="email" class="form-input" type="email" name="email" required autocomplete="email"  pattern="[a-z0-9._%+\-]+@gmail\.com$" title="Must be a valid email format like example@gmail.com" />
                        <span class="form-error" id="email-error"></span>
                    </div>

                    <div class="form-group half-width">
                    <label for="contactnumber">Contact Number <span class="required">*</span></label>
                    <div class="form-input-group">
                        <div class="input-prefix">+63</div>
                        <input id="contactnumber" class="form-input" type="text" name="contactnumber" required autocomplete="contactnumber"
                            maxlength="10" pattern="\d{10}" title="Invalid contact number. Must be exactly 10 digits." />
                    </div>
                    <span class="form-error" id="contactnumber-error"></span>
                </div>
                </div>

                <div class="form-row">
                    <div class="form-group half-width">
                        <label for="password">Password <span class="required">*</span></label>
                        <input id="password" class="form-input" type="password" name="password" required autocomplete="new-password" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{8,}" />
                        <span class="form-error" id="password-error"></span>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            <strong>Password Requirements:</strong><br>
                            • At least 8 characters<br>
                            • One uppercase letter (A-Z)<br>
                            • One lowercase letter (a-z)<br>
                            • One number (0-9)<br>
                        </small>
                    </div>

                    <div class="form-group half-width">
                        <label for="password_confirmation">Confirm Password <span class="required">*</span></label>
                        <input id="password_confirmation" class="form-input" type="password" name="password_confirmation" required autocomplete="new-password" />
                        <span class="form-error" id="password_confirmation-error"></span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="role_id">Select Your User Type <span class="required">*</span></label>
                        <select id="role_id" name="role_id" class="form-select" required>
                            <option value="" disabled selected>---- e.g. Applicant, Admin, Evaluator, Interviewer  ----</option>
                            <option value="1">Applicant</option>
                            <option value="2">Admin</option>
                            <option value="3">Evaluator</option>
                            <option value="4">Interviewer</option>
                            <option value="5">Medical Staff</option>
                            <option value="6">Registrar</option>
                        </select>
                        <span class="form-error" id="role_id-error"></span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width" id="program-group">
                        <label for="program">Program <span class="required">*</span></label>
                        <select id="program" name="program" class="form-select">
                            <option value="" disabled selected>---- Select Program ----</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->code }}">{{ $program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group mt-4 flex items-center justify-end">
                        <button type="submit" class="form-button ms-4">
                        <i class="fa-solid fa-user-plus"></i> Add User
                        </button>
                    </div>
                </form>

                <div id="snackbar">Email is already used.</div>
                <div id="snackbar-password">Password didn't match.</div>
            </div>
        </div>
    </div>

<div class="mdc-dialog dialog-overlay" id="user-added-dialog" role="alertdialog" aria-modal="true" aria-labelledby="dialog-title" aria-describedby="dialog-content">
    <div class="mdc-dialog__container">
        <div class="mdc-dialog__surface dialog-box">
            <h2 class="mdc-dialog__title" id="dialog-title">Success</h2>
            <div class="mdc-dialog__content" id="dialog-content">
                User added successfully.
            </div>
            <footer class="mdc-dialog__actions">
                <button type="button" class="mdc-button dialog-button" data-mdc-dialog-action="close">
                    <span class="mdc-button__label">OK</span>
                </button>
            </footer>
        </div>
    </div>
</div>
</body>

    <script src="https://cdn.jsdelivr.net/npm/@material/dialog@14.0.0/dist/mdc.dialog.min.js"></script>
     <script src="{{ asset('js/maintenance/add_user.js') }}"></script>
    
</html>