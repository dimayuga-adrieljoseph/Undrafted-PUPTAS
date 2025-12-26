<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="{{ asset('assets/maintenance/user_accounts/add.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .table-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .table-wrapper {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: #9E122C;
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .btn-add {
            background: #FBCB77;
            color: #9E122C;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-add:hover {
            background: #EE6A43;
            color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f5f5f5;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            font-weight: 600;
            color: #333;
        }
        
        tbody tr:hover {
            background: #f9f9f9;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .role-applicant { background: #e3f2fd; color: #1976d2; }
        .role-admin { background: #fce4ec; color: #c2185b; }
        .role-evaluator { background: #e8f5e9; color: #388e3c; }
        .role-interviewer { background: #fff3e0; color: #f57c00; }
        .role-medical { background: #f3e5f5; color: #7b1fa2; }
        .role-registrar { background: #e0f2f1; color: #00796b; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-edit, .btn-delete {
            padding: 0.375rem 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.3s;
        }
        
        .btn-edit {
            background: #2196f3;
            color: white;
        }
        
        .btn-edit:hover {
            background: #1976d2;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background: #d32f2f;
        }
        
        .alert {
            padding: 1rem;
            margin: 1rem 2rem;
            border-radius: 4px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .search-box {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .search-input {
            width: 100%;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .no-users {
            padding: 3rem;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- User Statistics -->
    <div class="flex flex-col min-h-screen">
        <div class="user-types-section w-full max-w-4xl mx-auto p-8">
            <div class="text-center">
                <h2 class="text-2xl font-bold mb-4 text-maroon">User Statistics</h2>
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
    </div>

    <!-- Success/Error Messages -->
    @if(session('status'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    <!-- Users Table -->
    <div class="table-container">
        <div class="table-wrapper">
            <div class="table-header">
                <h2><i class="fas fa-users"></i> Manage Users</h2>
                <a href="{{ route('legacy.add_user') }}" class="btn-add">
                    <i class="fas fa-user-plus"></i> Add New User
                </a>
            </div>
            
            <div class="search-box">
                <input type="text" id="searchInput" class="search-input" placeholder="Search by name, email, or role...">
            </div>

            @if($users->count() > 0)
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact Number</th>
                            <th>Role</th>
                            <th>Program</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>
                                    {{ $user->firstname }} 
                                    @if($user->middlename) {{ $user->middlename }} @endif 
                                    {{ $user->lastname }}
                                    @if($user->extension_name) {{ $user->extension_name }} @endif
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->contactnumber }}</td>
                                <td>
                                    @php
                                        $roleClass = [
                                            1 => 'role-applicant',
                                            2 => 'role-admin',
                                            3 => 'role-evaluator',
                                            4 => 'role-interviewer',
                                            5 => 'role-medical',
                                            6 => 'role-registrar',
                                        ][$user->role_id] ?? '';
                                    @endphp
                                    <span class="role-badge {{ $roleClass }}">
                                        {{ $roles[$user->role_id] ?? 'Unknown' }}
                                    </span>
                                </td>
                                <td>{{ $user->program ?? 'N/A' }}</td>
                                <td>{{ $user->created_at->format('M d, Y') }}</td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="{{ route('users.edit', $user->id) }}" class="btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form action="{{ route('users.destroy', $user->id) }}" method="POST" 
                                              onsubmit="return confirm('Are you sure you want to delete this user?');" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="no-users">
                    <i class="fas fa-user-slash" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                    <p>No users found. <a href="{{ route('legacy.add_user') }}">Add your first user</a></p>
                </div>
            @endif
        </div>
    </div>

    <script>
        // Simple search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            }
        });
    </script>
</body>
</html>
