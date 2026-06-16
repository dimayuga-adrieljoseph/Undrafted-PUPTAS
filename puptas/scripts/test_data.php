<?php
$svc = app(\App\Services\UserService::class);
$res = $svc->getAllApplicantsByStage('interviewer');
echo json_encode($res->first(), JSON_PRETTY_PRINT);
