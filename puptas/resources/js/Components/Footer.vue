<script setup>
// Footer component with Terms of Use and Privacy Statement links
// Visible in all pages except login and register
</script>

<template>
    <footer class="bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 py-4 px-6">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-center gap-4">
            <!-- Copyright -->
            <p class="text-sm text-gray-500 dark:text-gray-400">
                © 1998-{{ new Date().getFullYear() }} PUP Taguig. All rights reserved.
            </p>
            
            <!-- Links -->
            <div class="flex items-center gap-6">
                <a
                    href="https://www.pup.edu.ph/terms/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-[#9E122C] dark:hover:text-[#9E122C] transition-colors duration-200"
                >
                    Terms of Use
                </a>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <a
                    href="https://www.pup.edu.ph/privacy/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-[#9E122C] dark:hover:text-[#9E122C] transition-colors duration-200"
                >
                    Privacy Statement
                </a>
            </div>
        </div>
    </footer>
</template>
