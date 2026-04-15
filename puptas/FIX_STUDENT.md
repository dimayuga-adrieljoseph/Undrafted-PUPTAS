# Fix Student Medical Issue

## 🔴 Problem Found

**Student has NO student number!**

```json
"student_number": null
```

This is why the medical webhook failed:
- Medical system sends webhook with `student_number`
- PUPTAS looks up user by `student_number`
- User has `student_number: null`
- Lookup fails → webhook rejected

## ✅ Solution: 2 Steps

### Step 1: Deploy the Fix Routes

```bash
git add routes/web.php
git commit -m "Add student number assignment and medical completion routes"
git push
```

Wait 1-2 minutes for Railway to deploy.

### Step 2: Assign Student Number

Visit this URL in your browser (or use curl):

```
https://puptas.undraftedbsit2027.com/debug-medical/assign-student-number/47f31f54-690a-4740-babb-18c4eaaffe85/debug2026
```

**Method:** POST (use Postman, curl, or browser extension)

**Using curl:**
```bash
curl -X POST "https://puptas.undraftedbsit2027.com/debug-medical/assign-student-number/47f31f54-690a-4740-babb-18c4eaaffe85/debug2026"
```

**Expected Response:**
```json
{
  "status": "success",
  "message": "Student number assigned",
  "student_number": "2026-MED-0001",
  "user_id": 17
}
```

### Step 3: Complete Medical (Manual Approval)

Since the medical system already approved this student, manually complete the medical stage:

```
https://puptas.undraftedbsit2027.com/debug-medical/complete-medical/47f31f54-690a-4740-babb-18c4eaaffe85/debug2026
```

**Method:** POST

**Using curl:**
```bash
curl -X POST "https://puptas.undraftedbsit2027.com/debug-medical/complete-medical/47f31f54-690a-4740-babb-18c4eaaffe85/debug2026"
```

**Expected Response:**
```json
{
  "status": "success",
  "message": "Medical completed successfully",
  "application_status": "cleared_for_enrollment",
  "visible_to_registrar": true
}
```

### Step 4: Verify

Check the student again:

```
https://puptas.undraftedbsit2027.com/debug-medical/47f31f54-690a-4740-babb-18c4eaaffe85/debug2026
```

You should now see:
```json
{
  "user": {
    "student_number": "2026-MED-0001"  // ✅ Now has number
  },
  "eligibility": {
    "medical_completed": true,  // ✅ Completed
    "visible_to_registrar": true  // ✅ Visible
  }
}
```

### Step 5: Check Registrar Dashboard

The student should now appear in the registrar dashboard!

### Step 6: Clean Up (Remove Debug Routes)

After fixing, remove the debug routes from `routes/web.php` and deploy.

---

## 🔍 Why This Happened

### Root Cause:
The student registered but was never assigned a student number. This could happen if:
1. Student number assignment is manual
2. There's a bug in the registration process
3. Student was created via API without student number
4. Student number is assigned at a later stage

### The Webhook Issue:
1. Medical system approved the student
2. Medical system sent webhook with `student_number: "XXXX"`
3. PUPTAS tried to find user with that student number
4. User has `student_number: null`
5. Lookup failed → webhook returned 404
6. Medical system thinks it worked (or didn't retry)
7. Student stuck in "medical in progress"

---

## 🛠️ Long-Term Fix

### Prevent This in the Future:

1. **Assign student numbers automatically** during registration
2. **Validate student number exists** before allowing medical stage
3. **Add webhook fallback** to lookup by IDP user ID if student number fails
4. **Add better error logging** for webhook failures
5. **Send webhook failure notifications** to admins

### Recommended Code Changes:

#### 1. Auto-assign student number on registration

```php
// In user creation/registration
if (!$user->student_number) {
    $year = date('Y');
    $lastNumber = User::where('student_number', 'LIKE', "$year-STU-%")
        ->orderBy('student_number', 'desc')
        ->value('student_number');
    
    $nextNum = $lastNumber ? ((int) substr($lastNumber, -4)) + 1 : 1;
    $user->student_number = sprintf("%s-STU-%04d", $year, $nextNum);
    $user->save();
}
```

#### 2. Add fallback lookup in webhook

```php
// In ExternalMedicalApiController::webhookResult()
$profile = $this->getEligibleApplicantQuery()
    ->whereHas('user', function ($q) use ($studentNumber) {
        $q->where('student_number', $studentNumber);
    })->first();

// Add fallback if not found
if (!$profile && $request->has('idp_user_id')) {
    $profile = $this->getEligibleApplicantQuery()
        ->whereHas('user', function ($q) use ($request) {
            $q->where('idp_user_id', $request->input('idp_user_id'));
        })->first();
}
```

---

## 📊 Summary

**Problem:** Student has no student number  
**Impact:** Medical webhook failed to find student  
**Solution:** Assign student number + manually complete medical  
**Prevention:** Auto-assign student numbers during registration  

After running the fix commands, the student will be visible to the registrar!
