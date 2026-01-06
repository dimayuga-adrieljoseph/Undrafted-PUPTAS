<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";
import ApplicantLayout from "@/Layouts/ApplicantLayout.vue";
import EvaluatorLayout from "@/Layouts/EvaluatorLayout.vue";
import InterviewerLayout from "@/Layouts/InterviewerLayout.vue";
import MedicalLayout from "@/Layouts/MedicalLayout.vue";
import RecordStaffLayout from "@/Layouts/RecordStaffLayout.vue";

import DeleteUserForm from "@/Pages/Profile/Partials/DeleteUserForm.vue";
import LogoutOtherBrowserSessionsForm from "@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue";
import SectionBorder from "@/Components/SectionBorder.vue";
import TwoFactorAuthenticationForm from "@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue";
import UpdatePasswordForm from "@/Pages/Profile/Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "@/Pages/Profile/Partials/UpdateProfileInformationForm.vue";

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

// Get current user from page props
const user = usePage().props.auth.user;

// Decide layout based on role_id
// const Layout = computed(() => {
//     return user.role_id === 1 ? ApplicantLayout : AppLayout;
// });

const Layout = computed(() => {
    switch (user.role_id) {
        case 1:
            return ApplicantLayout;
        case 2:
            return AppLayout;
        case 3:
            return EvaluatorLayout;
        case 4:
            return InterviewerLayout;
        case 5:
            return MedicalLayout;
        case 6:
            return RecordStaffLayout;
        default:
            return ApplicantLayout; // fallback layout
    }
});
</script>

<
<template>
    <component :is="Layout" title="Profile">
        <template #header>
            <h2 class="font-semibold text-xl text-[#9E122C] leading-tight">
                Profile
            </h2>
        </template>

        <div>
            <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
                <div v-if="$page.props.jetstream.canUpdateProfileInformation">
                    <UpdateProfileInformationForm
                        :user="$page.props.auth.user"
                    />

                    <SectionBorder />
                </div>

                <div v-if="$page.props.jetstream.canUpdatePassword">
                    <UpdatePasswordForm class="mt-10 sm:mt-0" />

                    <SectionBorder />
                </div>

                <div
                    v-if="
                        $page.props.jetstream.canManageTwoFactorAuthentication
                    "
                >
                    <TwoFactorAuthenticationForm
                        :requires-confirmation="confirmsTwoFactorAuthentication"
                        class="mt-10 sm:mt-0"
                    />

                    <SectionBorder />
                </div>

                <LogoutOtherBrowserSessionsForm
                    :sessions="sessions"
                    class="mt-10 sm:mt-0"
                />

                <template
                    v-if="$page.props.jetstream.hasAccountDeletionFeatures"
                >
                    <SectionBorder />

                    <DeleteUserForm class="mt-10 sm:mt-0" />
                </template>
            </div>
        </div>
    </component>
</template>
