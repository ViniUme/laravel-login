<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Form } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import SignUpAuthV1ApiController from '@/actions/App/Http/Controllers/Api/V1/Auth/SignUpAuthV1ApiController';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('flex flex-col gap-6', props.class)">
        <Card>
            <CardHeader class="text-center">
                <CardTitle class="text-xl">Crie sua conta</CardTitle>
                <CardDescription>
                    Cadastre seu email abaixo para criar sua conta
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form :action="SignUpAuthV1ApiController()">
                    <FieldGroup>
                        <Field>
                            <FieldLabel for="name">Nome completo</FieldLabel>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                placeholder="João Siqueira"
                                required
                            />
                        </Field>
                        <Field>
                            <FieldLabel for="email">E-mail</FieldLabel>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                placeholder="joao@exeplo.com"
                                required
                            />
                        </Field>
                        <Field>
                            <Field class="grid grid-cols-2 gap-4">
                                <Field>
                                    <FieldLabel for="password">
                                        Senha
                                    </FieldLabel>
                                    <Input
                                        id="password"
                                        name="password"
                                        type="password"
                                        required
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel for="password_confirmation">
                                        Confirmar senha
                                    </FieldLabel>
                                    <Input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        required
                                    />
                                </Field>
                            </Field>
                            <FieldDescription>
                                Precisa ter no mínimo 8 caracteres.
                            </FieldDescription>
                        </Field>
                        <Field>
                            <Button type="submit">Criar conta</Button>
                            <FieldDescription class="text-center">
                                Já possui uma conta? <a href="#">Entrar</a>
                            </FieldDescription>
                        </Field>
                    </FieldGroup>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
