<script setup lang="ts">
import SignupForm from "@/components/signup-03/components/SignupForm.vue";
import ModeToggle from "@/components/ui/button/ModeToggle.vue";
</script>

<template>
    <div class="flex min-h-svh w-full items-center justify-center p-6 md:p-10">
        <div class="w-full max-w-sm">
            <SignupForm />
        </div>
        <div class="size-22 fixed right-0 bottom-0">
            <ModeToggle />
        </div>
    </div>
</template>

<style>
</style>
