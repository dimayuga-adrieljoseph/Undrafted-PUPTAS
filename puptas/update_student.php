<?php
$user = App\Models\User::where('email', 'student29@gmail.com')->first();
if($user) {
    $app = $user->applications()->latest()->first();
    if ($app && !$app->third_choice_id) {
        $app->update(['third_choice_id' => App\Models\Program::first()->id]);
        echo 'Updated';
    } else {
        echo 'Already has third choice or no app';
    }
} else {
    echo 'User not found';
}
