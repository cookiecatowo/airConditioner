<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    orders: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');

// 從 props 衍生，永遠與 URL 同步
const filterProcessing = computed(() =>
    props.filters.ps  ? props.filters.ps.split(',').map(Number) : []
);
const filterPayment = computed(() =>
    props.filters.pmt ? props.filters.pmt.split(',').map(Number) : []
);
const filterType = computed(() =>
    props.filters.tp  ? props.filters.tp.split(',') : []
);
const filterCategory = computed(() =>
    props.filters.wc  ? props.filters.wc.split(',') : []
);

const applyFilters = (overrides = {}) => {
    const get = (key, current) => overrides[key] !== undefined ? overrides[key] : current;
    router.get(route('orders.index'), {
        search: search.value || undefined,
        ps:  get('ps',  filterProcessing.value.join(',')) || undefined,
        pmt: get('pmt', filterPayment.value.join(','))    || undefined,
        tp:  get('tp',  filterType.value.join(','))       || undefined,
        wc:  get('wc',  filterCategory.value.join(','))   || undefined,
    }, { preserveState: true, replace: true });
};

watch(search, () => applyFilters());

const toggleFilter = (key, value) => {
    const currentMap = { ps: filterProcessing, pmt: filterPayment, tp: filterType, wc: filterCategory };
    const current = [...currentMap[key].value];
    const idx = current.indexOf(key === 'ps' || key === 'pmt' ? Number(value) : value);
    if (idx === -1) current.push(key === 'ps' || key === 'pmt' ? Number(value) : value);
    else current.splice(idx, 1);
    applyFilters({ [key]: current.join(',') });
};

// 訂單類型
const typeOptions = [
    { value: 'install',     label: '安裝', badgeCls: 'bg-blue-100 text-blue-800 border-blue-200',   activeCls: 'bg-blue-500 text-white border-blue-500',     inactiveCls: 'bg-white text-blue-700 border-blue-300 hover:bg-blue-50' },
    { value: 'repair',      label: '維修', badgeCls: 'bg-green-100 text-green-800 border-green-200', activeCls: 'bg-green-500 text-white border-green-500',   inactiveCls: 'bg-white text-green-700 border-green-300 hover:bg-green-50' },
    { value: 'maintenance', label: '保養', badgeCls: 'bg-purple-100 text-purple-800 border-purple-200', activeCls: 'bg-purple-500 text-white border-purple-500', inactiveCls: 'bg-white text-purple-700 border-purple-300 hover:bg-purple-50' },
];
// 業務分類
const categoryOptions = [
    { value: 'ac',          label: '空調', badgeCls: 'bg-sky-100 text-sky-700',       activeCls: 'bg-sky-500 text-white border-sky-500',         inactiveCls: 'bg-white text-sky-700 border-sky-300 hover:bg-sky-50' },
    { value: 'surveillance',label: '監視', badgeCls: 'bg-indigo-100 text-indigo-700', activeCls: 'bg-indigo-500 text-white border-indigo-500',   inactiveCls: 'bg-white text-indigo-700 border-indigo-300 hover:bg-indigo-50' },
    { value: 'other',       label: '其他', badgeCls: 'bg-gray-100 text-gray-600',     activeCls: 'bg-gray-500 text-white border-gray-500',       inactiveCls: 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' },
];

const getTypeBadgeCls  = (v) => typeOptions.find(o => o.value === v)?.badgeCls || 'bg-gray-100 text-gray-600 border-gray-200';
const getTypeName      = (v) => typeOptions.find(o => o.value === v)?.label || v;
const getCategoryBadgeCls = (v) => categoryOptions.find(o => o.value === v)?.badgeCls || '';
const getCategoryLabel    = (v) => categoryOptions.find(o => o.value === v)?.label || '';

// 處理狀況
const processingOptions = [
    { value: 1, label: '處理中', activeCls: 'bg-yellow-400 text-white border-yellow-400',   inactiveCls: 'bg-white text-yellow-700 border-yellow-300 hover:bg-yellow-50',  badgeCls: 'bg-yellow-100 text-yellow-800' },
    { value: 2, label: '已完成', activeCls: 'bg-green-500 text-white border-green-500',     inactiveCls: 'bg-white text-green-700 border-green-300 hover:bg-green-50',    badgeCls: 'bg-green-100 text-green-800'  },
    { value: 3, label: '垃圾桶', activeCls: 'bg-gray-400 text-white border-gray-400',       inactiveCls: 'bg-white text-gray-500 border-gray-300 hover:bg-gray-50',       badgeCls: 'bg-gray-100 text-gray-500'    },
];
// 收款狀況
const paymentOptions = [
    { value: 1, label: '未收款',   activeCls: 'bg-red-500 text-white border-red-500',         inactiveCls: 'bg-white text-red-600 border-red-300 hover:bg-red-50',           badgeCls: 'bg-red-100 text-red-700'         },
    { value: 2, label: '已收訂金', activeCls: 'bg-orange-400 text-white border-orange-400',   inactiveCls: 'bg-white text-orange-600 border-orange-300 hover:bg-orange-50', badgeCls: 'bg-orange-100 text-orange-700'   },
    { value: 3, label: '已結清',   activeCls: 'bg-emerald-500 text-white border-emerald-500', inactiveCls: 'bg-white text-emerald-600 border-emerald-300 hover:bg-emerald-50', badgeCls: 'bg-emerald-100 text-emerald-700' },
];

const getProcessingBadgeCls = (v) => processingOptions.find(o => o.value == v)?.badgeCls || '';
const getPaymentBadgeCls    = (v) => paymentOptions.find(o => o.value == v)?.badgeCls || '';

const updateStatus = (orderId, field, value) => {
    router.patch(route('orders.updateStatus', orderId), { [field]: value }, {
        preserveState: true,
        preserveScroll: true,
        onError: (errors) => { if (errors.status) alert(errors.status); },
    });
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
                <div class="mb-4">
                    <TextInput
                        v-model="search"
                        type="text"
                        placeholder="搜尋顧客姓名、電話、施工地址..."
                        class="w-full md:w-1/2"
                    />
                </div>

                <!-- 篩選列 -->
                <div class="mb-6 flex flex-wrap gap-x-6 gap-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-400 whitespace-nowrap">業務</span>
                        <div class="flex gap-1">
                            <button v-for="o in categoryOptions" :key="o.value"
                                @click="toggleFilter('wc', o.value)"
                                :class="filterCategory.includes(o.value) ? o.activeCls : o.inactiveCls"
                                class="px-3 py-1 rounded-full text-xs font-semibold border transition">{{ o.label }}</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-400 whitespace-nowrap">類型</span>
                        <div class="flex gap-1">
                            <button v-for="o in typeOptions" :key="o.value"
                                @click="toggleFilter('tp', o.value)"
                                :class="filterType.includes(o.value) ? o.activeCls : o.inactiveCls"
                                class="px-3 py-1 rounded-full text-xs font-semibold border transition">{{ o.label }}</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-400 whitespace-nowrap">處理</span>
                        <div class="flex gap-1">
                            <button v-for="o in processingOptions" :key="o.value"
                                @click="toggleFilter('ps', o.value)"
                                :class="filterProcessing.includes(o.value) ? o.activeCls : o.inactiveCls"
                                class="px-3 py-1 rounded-full text-xs font-semibold border transition">{{ o.label }}</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-400 whitespace-nowrap">收款</span>
                        <div class="flex gap-1">
                            <button v-for="o in paymentOptions" :key="o.value"
                                @click="toggleFilter('pmt', o.value)"
                                :class="filterPayment.includes(o.value) ? o.activeCls : o.inactiveCls"
                                class="px-3 py-1 rounded-full text-xs font-semibold border transition">{{ o.label }}</button>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">日期</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">類型</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">顧客姓名</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">地址</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">總金額</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">處理狀況</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">收款狀況</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="order in orders.data" :key="order.id" class="hover:bg-gray-50 transition" :class="{ 'opacity-50': order.processing_status == 3 }">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ order.date }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                            <div class="flex flex-col gap-1">
                                                <span v-if="order.work_category" :class="getCategoryBadgeCls(order.work_category)" class="px-2 py-0.5 rounded-full text-xs font-semibold w-fit">
                                                    {{ getCategoryLabel(order.work_category) }}
                                                </span>
                                                <span :class="getTypeBadgeCls(order.type)" class="px-2 py-0.5 rounded-full text-xs font-bold border w-fit">
                                                    {{ getTypeName(order.type) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ order.customer.name }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 truncate max-w-xs">{{ order.address }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-right font-bold text-blue-600">${{ order.total_amount }}</td>

                                        <!-- 處理狀況 -->
                                        <td class="px-4 py-4 whitespace-nowrap text-center">
                                            <select
                                                :value="order.processing_status"
                                                @change="updateStatus(order.id, 'processing_status', $event.target.value)"
                                                :class="getProcessingBadgeCls(order.processing_status)"
                                                class="text-xs font-semibold rounded-full px-2 py-1 border-0 cursor-pointer focus:ring-2 focus:ring-offset-1 focus:ring-blue-400"
                                            >
                                                <option v-for="o in processingOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                            </select>
                                        </td>

                                        <!-- 收款狀況 -->
                                        <td class="px-4 py-4 whitespace-nowrap text-center">
                                            <select
                                                :value="order.payment_status"
                                                @change="updateStatus(order.id, 'payment_status', $event.target.value)"
                                                :class="getPaymentBadgeCls(order.payment_status)"
                                                class="text-xs font-semibold rounded-full px-2 py-1 border-0 cursor-pointer focus:ring-2 focus:ring-offset-1 focus:ring-blue-400"
                                            >
                                                <option v-for="o in paymentOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                            </select>
                                        </td>

                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-center font-medium">
                                            <div class="flex justify-center gap-3">
                                                <Link :href="route('orders.show', order.id)" class="text-blue-600 hover:text-blue-900 font-bold">檢視</Link>
                                                <a :href="route('orders.export', order.id)" class="text-indigo-600 hover:text-indigo-900">Word</a>
                                                <Link :href="route('orders.edit', order.id)" class="text-gray-600 hover:text-gray-900">編輯</Link>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="orders.data.length === 0">
                                        <td colspan="8" class="px-6 py-10 text-center text-gray-500">尚無報價單資料</td>
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