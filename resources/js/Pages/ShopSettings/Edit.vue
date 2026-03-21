<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({}),
    },
    status: {
        type: String,
    },
});

const form = useForm({
    shop_name: props.settings.shop_name || '',
    owner_name: props.settings.owner_name || '',
    shop_address: props.settings.shop_address || '',
    shop_phone: props.settings.shop_phone || '',
    bank_name: props.settings.bank_name || '',
    bank_account: props.settings.bank_account || '',
    bank_account_name: props.settings.bank_account_name || '',
});

const submit = () => {
    form.patch(route('shop-settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="店家資訊管理" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">店家資訊管理</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">報價單店家資訊設定</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                設定後將自動套用於產出的 Word 報價單中。
                            </p>
                        </header>

                        <div v-if="status" class="mt-4 font-medium text-sm text-green-600">
                            {{ status }}
                        </div>

                        <form @submit.prevent="submit" class="mt-6 space-y-6 max-w-xl">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="shop_name" value="店家名稱" />
                                    <TextInput
                                        id="shop_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.shop_name"
                                        autofocus
                                    />
                                    <InputError class="mt-2" :message="form.errors.shop_name" />
                                </div>
                                <div>
                                    <InputLabel for="owner_name" value="老闆名稱" />
                                    <TextInput
                                        id="owner_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.owner_name"
                                    />
                                    <InputError class="mt-2" :message="form.errors.owner_name" />
                                </div>
                            </div>

                            <div>
                                <InputLabel for="shop_address" value="地址" />
                                <TextInput
                                    id="shop_address"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.shop_address"
                                />
                                <InputError class="mt-2" :message="form.errors.shop_address" />
                            </div>

                            <div>
                                <InputLabel for="shop_phone" value="聯絡電話" />
                                <TextInput
                                    id="shop_phone"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.shop_phone"
                                />
                                <InputError class="mt-2" :message="form.errors.shop_phone" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <InputLabel for="bank_name" value="匯款銀行" />
                                    <TextInput
                                        id="bank_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.bank_name"
                                        placeholder="例：玉山銀行"
                                    />
                                    <InputError class="mt-2" :message="form.errors.bank_name" />
                                </div>
                                <div>
                                    <InputLabel for="bank_account_name" value="戶名" />
                                    <TextInput
                                        id="bank_account_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.bank_account_name"
                                    />
                                    <InputError class="mt-2" :message="form.errors.bank_account_name" />
                                </div>
                                <div>
                                    <InputLabel for="bank_account" value="匯款帳號" />
                                    <TextInput
                                        id="bank_account"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.bank_account"
                                    />
                                    <InputError class="mt-2" :message="form.errors.bank_account" />
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <PrimaryButton :disabled="form.processing">儲存設定</PrimaryButton>

                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">已儲存.</p>
                                </Transition>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>