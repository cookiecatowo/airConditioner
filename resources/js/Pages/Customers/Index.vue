<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    customers: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
let timer = null;

watch(search, (v) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('customers.index'), v ? { search: v } : {}, {
            preserveState: true,
            replace: true,
        });
    }, 300);
});

const money = (v) => new Intl.NumberFormat('zh-TW').format(v || 0);
const total = computed(() => props.customers.total);
</script>

<template>
    <Head title="顧客管理" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客管理</h2>
        </template>

        <div class="py-12 bg-gray-100 min-h-screen">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div class="bg-white p-4 shadow sm:rounded-lg flex flex-wrap items-center gap-4">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="搜尋顧客名稱、電話或統編…"
                        class="flex-1 min-w-[240px] border-gray-300 rounded-md focus:ring-blue-400 focus:border-blue-400"
                    />
                    <span class="text-sm text-gray-500">共 {{ total }} 位</span>
                </div>

                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">顧客名稱</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">電話</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">統編</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">報價單</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">累計金額</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">操作</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr v-for="c in customers.data" :key="c.id" class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <Link :href="route('customers.show', c.id)" class="font-medium text-blue-600 hover:text-blue-800">
                                            {{ c.name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ c.phone || '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ c.tax_id || '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                              :class="c.orders_count ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'">
                                            {{ c.orders_count }} 張
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-gray-800">
                                        ${{ money(c.orders_sum_total_amount) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                        <Link :href="route('customers.show', c.id)" class="text-blue-600 hover:text-blue-900 font-medium">檢視 / 編輯</Link>
                                    </td>
                                </tr>
                                <tr v-if="customers.data.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-400">找不到符合的顧客</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="customers.links.length > 3" class="px-4 py-3 border-t border-gray-100 flex flex-wrap gap-1">
                        <component
                            v-for="(link, i) in customers.links"
                            :key="i"
                            :is="link.url ? 'a' : 'span'"
                            :href="link.url"
                            v-html="link.label"
                            class="px-3 py-1 text-sm rounded"
                            :class="link.active ? 'bg-blue-600 text-white' : (link.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300')"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
