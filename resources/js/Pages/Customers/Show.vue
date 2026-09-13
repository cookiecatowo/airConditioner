<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    customer: Object,
    addresses: Array,
    orders: Array,
    others: Array,
});

const form = useForm({
    name: props.customer.name,
    phone: props.customer.phone || '',
    tax_id: props.customer.tax_id || '',
});

const save = () => form.patch(route('customers.update', props.customer.id), { preserveScroll: true });

const money = (v) => new Intl.NumberFormat('zh-TW').format(v || 0);
const typeLabel = { install: '安裝', repair: '維修', maintenance: '保養' };
const wcLabel = { ac: '空調', surveillance: '監視', other: '其他' };
const procLabel = { 1: '處理中', 2: '已完成', 3: '垃圾桶' };
const payLabel = { 1: '未收款', 2: '已收訂金', 3: '已結清' };
const procCls = { 1: 'bg-blue-100 text-blue-700', 2: 'bg-green-100 text-green-700', 3: 'bg-gray-200 text-gray-500' };
const payCls = { 1: 'bg-red-100 text-red-700', 2: 'bg-amber-100 text-amber-700', 3: 'bg-emerald-100 text-emerald-700' };

const totalAmount = computed(() => props.orders.reduce((s, o) => s + parseFloat(o.total_amount || 0), 0));

// 合併
const showMerge = ref(false);
const mergeForm = useForm({ target_id: '' });

const doMerge = () => {
    const t = props.others.find((o) => o.id == mergeForm.target_id);
    if (!t) return;
    if (!confirm(`確定把「${props.customer.name}」的 ${props.orders.length} 張報價單全部轉到「${t.name}」，並刪除「${props.customer.name}」這筆顧客資料嗎？\n\n報價單本身不會被刪除，只是改掛在「${t.name}」底下。`)) return;
    mergeForm.post(route('customers.merge', props.customer.id));
};
</script>

<template>
    <Head :title="`顧客 - ${customer.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ customer.name }}</h2>
                <Link :href="route('customers.index')" class="inline-flex items-center px-4 py-2 bg-gray-200 rounded-md font-semibold text-xs text-gray-700 uppercase hover:bg-gray-300 transition">
                    返回顧客列表
                </Link>
            </div>
        </template>

        <div class="py-12 bg-gray-100 min-h-screen">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- 顧客資料 -->
                <div class="bg-white p-6 shadow sm:rounded-lg border-l-4 border-blue-500">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-gray-800">顧客資料</h3>
                        <div class="flex items-center gap-3">
                            <span v-if="form.recentlySuccessful" class="text-xs text-green-600">已儲存 ✓</span>
                            <button
                                @click="save"
                                :disabled="form.processing"
                                class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded transition disabled:opacity-50"
                            >儲存</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">顧客名稱</label>
                            <input v-model="form.name" type="text" class="w-full border-gray-300 rounded-md focus:ring-blue-400 focus:border-blue-400" />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">聯絡電話</label>
                            <input v-model="form.phone" type="text" class="w-full border-gray-300 rounded-md focus:ring-blue-400 focus:border-blue-400" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">統一編號</label>
                            <input v-model="form.tax_id" type="text" class="w-full border-gray-300 rounded-md focus:ring-blue-400 focus:border-blue-400" />
                        </div>
                    </div>

                    <p class="text-xs text-gray-400 mt-3">
                        改這裡的資料，這位顧客名下 {{ orders.length }} 張報價單都會跟著更新（統編也會一併同步）。
                        地址不放在這裡，因為同一位顧客可能有多個施工地點 —— 地址跟著各別的報價單走。
                    </p>
                </div>

                <!-- 用過的地址 -->
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <h3 class="font-bold text-gray-800 mb-3">曾經施工的地址 <span class="text-xs font-normal text-gray-400">（來自這位顧客的報價單）</span></h3>
                    <ul v-if="addresses.length" class="space-y-1">
                        <li v-for="(a, i) in addresses" :key="i" class="text-sm text-gray-700 flex items-start gap-2">
                            <span class="text-gray-300">•</span><span>{{ a }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400">還沒有填過地址</p>
                </div>

                <!-- 報價單 -->
                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100">
                        <h3 class="font-bold text-gray-800">報價單（{{ orders.length }} 張）</h3>
                        <span class="text-sm text-gray-600">累計 <span class="font-bold text-blue-600">${{ money(totalAmount) }}</span></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">日期</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">類型</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">地址</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">金額</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">狀態</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr v-for="o in orders" :key="o.id" class="hover:bg-gray-50 transition" :class="{ 'opacity-50': o.processing_status == 3 }">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ o.date }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs">
                                        <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 mr-1">{{ typeLabel[o.type] }}</span>
                                        <span v-if="o.work_category" class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ wcLabel[o.work_category] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">{{ o.address }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-blue-600">${{ money(o.total_amount) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-xs space-x-1">
                                        <span class="px-2 py-0.5 rounded-full" :class="procCls[o.processing_status]">{{ procLabel[o.processing_status] }}</span>
                                        <span class="px-2 py-0.5 rounded-full" :class="payCls[o.payment_status]">{{ payLabel[o.payment_status] }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm">
                                        <Link :href="route('orders.show', o.id)" class="text-blue-600 hover:text-blue-900 font-medium">檢視</Link>
                                    </td>
                                </tr>
                                <tr v-if="orders.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-400">這位顧客還沒有報價單</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 合併 -->
                <div class="bg-amber-50 p-6 rounded-lg border border-amber-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-amber-800">合併到其他顧客</h3>
                            <p class="text-xs text-amber-700 mt-1">同一家客戶被記成兩筆時用這個（例如「老順安」和「老順安大藥局」）。</p>
                        </div>
                        <button @click="showMerge = !showMerge" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded transition">
                            {{ showMerge ? '取消' : '合併' }}
                        </button>
                    </div>

                    <div v-if="showMerge" class="mt-4 flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[240px]">
                            <label class="block text-xs text-amber-700 mb-1">把「{{ customer.name }}」併入哪一位顧客？</label>
                            <select v-model="mergeForm.target_id" class="w-full border-amber-300 rounded-md focus:ring-amber-400 focus:border-amber-400">
                                <option value="">請選擇…</option>
                                <option v-for="o in others" :key="o.id" :value="o.id">
                                    {{ o.name }}<template v-if="o.phone"> — {{ o.phone }}</template>（{{ o.orders_count }} 張）
                                </option>
                            </select>
                            <p v-if="mergeForm.errors.target_id" class="text-red-500 text-xs mt-1">{{ mergeForm.errors.target_id }}</p>
                        </div>
                        <button
                            @click="doMerge"
                            :disabled="!mergeForm.target_id || mergeForm.processing"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded transition disabled:opacity-40"
                        >確定合併</button>
                    </div>
                    <p class="text-xs text-amber-700 mt-3">合併後「{{ customer.name }}」這筆顧客資料會被刪除，但報價單全部保留，只是改掛到對方名下。</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
