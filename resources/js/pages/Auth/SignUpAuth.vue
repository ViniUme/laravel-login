<script setup lang="ts">
import SignupForm from "@/components/signup-03/components/SignupForm.vue";
import ModeToggle from "@/components/ui/button/ModeToggle.vue";
import { GalleryVerticalEnd } from "@lucide/vue"
</script>

<template>
    <div class="bg-muted flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div class="flex w-full max-w-sm flex-col gap-6">
            <a href="#" class="flex items-center gap-2 self-center font-medium">
                <div class="bg-primary text-primary-foreground flex size-6 items-center justify-center rounded-md">
                    <GalleryVerticalEnd class="size-4" />
                </div>
                Laravel Login
            </a>
            <SignupForm />
        </div>
        <div class="fixed right-0 bottom-0 size-22">
            <ModeToggle />
        </div>
    </div>
</template>

<style>
</style>
