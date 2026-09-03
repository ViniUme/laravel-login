<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
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
                <form>
                    <FieldGroup>
                        <Field>
                            <FieldLabel for="name">Nome completo</FieldLabel>
                            <Input
                                id="name"
                                type="text"
                                placeholder="João Siqueira"
                                required
                            />
                        </Field>
                        <Field>
                            <FieldLabel for="email">E-mail</FieldLabel>
                            <Input
                                id="email"
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
                                        type="password"
                                        required
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel for="confirm-password">
                                        Confirmar senha
                                    </FieldLabel>
                                    <Input
                                        id="confirm-password"
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
                </form>
            </CardContent>
        </Card>
    </div>
</template>
