<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    orders: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get(route('orders.index'), { search: value }, {
        preserveState: true,
        replace: true
    });
});

const getStatusBadgeClass = (type) => {
    return type === 'install' 
        ? 'bg-blue-100 text-blue-800 border-blue-200' 
        : 'bg-green-100 text-green-800 border-green-200';
};

const getTypeName = (type) => {
    return type === 'install' ? '安裝' : '維修';
};
</script>

<template>
    <Head title="報價單管理" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">報價單管理</h2>
                <Link :href="route('orders.create')">
                    <PrimaryButton>+ 新增報價單</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-6">
                    <TextInput 
                        v-model="search" 
                        type="text" 
                        placeholder="搜尋顧客姓名、電話、施工地址..." 
                        class="w-full md:w-1/2"
                    />
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">日期</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">類型</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">顧客姓名</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">地址</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">總金額</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="order in orders.data" :key="order.id" class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ order.date }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span :class="getStatusBadgeClass(order.type)" class="px-2 py-1 rounded-full text-xs font-bold border">
                                                {{ getTypeName(order.type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ order.customer.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 truncate max-w-xs">{{ order.address }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-blue-600">${{ order.total_amount }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-medium">
                                            <div class="flex justify-center gap-3">
                                                <button class="text-indigo-600 hover:text-indigo-900">Word</button>
                                                <button class="text-gray-600 hover:text-gray-900">詳情</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="orders.data.length === 0">
                                        <td colspan="6" class="px-6 py-10 text-center text-gray-500">尚無報價單資料</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- 分頁 -->
                        <div v-if="orders.links.length > 3" class="mt-6 flex justify-center">
                            <div class="flex gap-1">
                                <Link 
                                    v-for="(link, k) in orders.links" :key="k"
                                    :href="link.url || '#'"
                                    v-html="link.label"
                                    :class="[
                                        'px-4 py-2 rounded-md text-sm border transition',
                                        link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                                    ]"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>