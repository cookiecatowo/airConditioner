<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    customer_name: '',
    customer_phone: '',
    install_address: '',
    ac_model: '',
    price: 0,
    quantity: 1,
    notes: '',
});

const submit = () => {
    
    form.post(route('quotations.store'));
};
</script>

<template>
    <Head title="新增報價單" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">快速建立報價單</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block font-medium text-sm text-gray-700">客戶姓名</label>
                            <input v-model="form.customer_name" type="text" class="w-full border-gray-300 rounded-md shadow-sm" placeholder="例如：王先生" required />
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700">冷氣型號</label>
                            <input v-model="form.ac_model" type="text" class="w-full border-gray-300 rounded-md shadow-sm" placeholder="例如：大金 R32 經典系列" required />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-medium text-sm text-gray-700">單價</label>
                                <input v-model="form.price" type="number" class="w-full border-gray-300 rounded-md shadow-sm" />
                            </div>
                            <div>
                                <label class="block font-medium text-sm text-gray-700">數量</label>
                                <input v-model="form.quantity" type="number" class="w-full border-gray-300 rounded-md shadow-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700">備註 (洗孔、架子等)</label>
                            <textarea v-model="form.notes" class="w-full border-gray-300 rounded-md shadow-sm"></textarea>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg font-bold shadow-lg active:bg-blue-800" :disabled="form.processing">
                            確認送出報價單
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
