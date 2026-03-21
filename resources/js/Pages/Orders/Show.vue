<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    order: Object,
    shop: Object,
});

const formatCurrency = (value) => {
    if (value === 0 || value === '0') return '未設定';
    return new Intl.NumberFormat('zh-TW', { minimumFractionDigits: 0 }).format(value);
};

// 設備小計
const equipmentsTotal = computed(() => {
    return props.order.equipments.reduce((sum, e) => sum + (parseFloat(e.pivot.sale_price || 0) * parseInt(e.pivot.quantity || 1)), 0);
});

// 材料小計
const materialsTotal = computed(() => {
    return props.order.materials.reduce((sum, m) => sum + (parseFloat(m.pivot.unit_price || 0) * parseInt(m.pivot.quantity || 1)), 0);
});
</script>

<template>
    <Head :title="`檢視報價單 - ${order.customer.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">報價單詳情 #{{ order.id }}</h2>
                <div class="flex gap-3">
                    <a :href="route('orders.export', order.id)" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition duration-150">
                        下載 Word
                    </a>
                    <Link :href="route('orders.edit', order.id)" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition duration-150">
                        編輯
                    </Link>
                    <Link :href="route('orders.index')" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 transition duration-150">
                        返回列表
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12 bg-gray-100 min-h-screen">
            <div class="max-w-[1000px] mx-auto sm:px-6 lg:px-8 space-y-8 ">
                <!-- 網頁操作資訊區 (列印時隱藏) -->
                <div class="bg-white p-6 shadow sm:rounded-lg border-l-4 border-blue-500 print:hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><p class="text-xs text-gray-400">客戶名稱</p><p class="font-bold">{{ order.customer.name }}</p></div>
                        <div><p class="text-xs text-gray-400">聯絡電話</p><p class="font-bold">{{ order.customer.phone }}</p></div>
                        <div><p class="text-xs text-gray-400">統編</p><p class="font-bold">{{ order.tax_id }}</p></div>
                        <div><p class="text-xs text-gray-400">建單日期</p><p class="font-bold">{{ order.date }}</p></div>
                        <div><p class="text-xs text-gray-400 ">地址</p><p class="font-bold">{{ order.address }}</p></div>
                        <div><p class="text-xs text-gray-400">備註</p><p class="font-bold">{{ order.public_notes }}</p></div>
                    </div>
                </div>

                <!-- 模擬 A4 報價單區域 -->
                <div class="flex justify-center">
                    <div class="bg-white shadow-2xl p-[1.5cm] w-[210mm] min-h-[297mm] text-black border border-gray-300 relative print:shadow-none print:p-0 print:border-none paper">
                        
                        <!-- 報單標題 -->
                        <div class="text-center mb-6">
                            <h1 class="text-4xl font-serif font-black tracking-[0.8em] border-b-4 border-double border-black pb-2 inline-block">
                                {{ order.report_title }}
                            </h1>
                        </div>

                        <!-- 抬頭資訊表格 -->
                        <table class="w-full mb-4 text-[15px] leading-relaxed text-left">
                            <tr>
                                <td class="w-1/2 py-1">顧客姓名：<span class="border-b border-black inline-block min-w-[150px]">{{ order.customer.name }}</span></td>
                                <td class="w-1/2 text-right py-1">報價日期：<span class="border-b border-black inline-block min-w-[120px]">{{ order.date }}</span></td>
                            </tr>
                            <tr>
                                <td class="py-1">聯絡電話：<span class="border-b border-black inline-block min-w-[150px]">{{ order.customer.phone }}</span></td>
                                <td class="text-right py-1">統一編號：<span class="border-b border-black inline-block min-w-[120px]">{{ order.tax_id || '　' }}</span></td>
                            </tr>
                            <tr>
                                <td colspan="2" class="py-1">施工地址：<span class="border-b border-black inline-block min-w-[85%] font-medium">{{ order.address }}</span></td>
                            </tr>
                            <tr>
                                <td colspan="2" class="py-1">備註說明：<span class="border-b border-black inline-block min-w-[85%]">{{ order.public_notes || '　' }}</span></td>
                            </tr>
                        </table>

                        <!-- 主明細表格 -->
                        <table class="w-full border-collapse border-[1.5px] border-black text-[13px] text-center">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border border-black px-1 py-1 w-10">項目</th>
                                    <th class="border border-black px-2 py-1">品名</th>
                                    <th class="border border-black px-2 py-1">規格</th>
                                    <th class="border border-black px-1 py-1 w-20">數量</th>
                                    <th class="border border-black px-2 py-1 w-24">單價</th>
                                    <th class="border border-black px-2 py-1 w-24">金額</th>
                                    <th class="border border-black px-2 py-1 w-28">備註</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- 安裝設備部分 -->
                                <template v-if="order.type === 'install' && order.equipments.length > 0">
                                    <tr v-for="(eq, i) in order.equipments" :key="'e'+i">
                                        <td v-if="i === 0" :rowspan="order.equipments.length + 1" class="border border-black px-1 py-2 bg-gray-50/30"></td>
                                        
                                        <template v-if="eq.pivot.is_adjustment">
                                            <td colspan="2" class="border border-black px-3 py-1.5 font-bold">{{ eq.pivot.custom_model_name }}</td>
                                            <td colspan="3" class="border border-black px-3 py-1.5 font-bold">{{ formatCurrency(eq.pivot.sale_price) }}</td>
                                        </template>
                                        <template v-else>
                                            <td class="border border-black px-2 py-1.5">{{ eq.model_name }}</td>
                                            <td class="border border-black px-2 py-1.5">{{ eq.specs || '　' }}</td>
                                            <td class="border border-black px-1 py-1.5">{{ eq.pivot.quantity }} 台</td>
                                            <td class="border border-black px-2 py-1.5">{{ formatCurrency(eq.pivot.sale_price) }}</td>
                                            <td class="border border-black px-2 py-1.5">{{ formatCurrency(eq.pivot.sale_price * eq.pivot.quantity) }}</td>
                                        </template>
                                        <td class="border border-black px-2 py-1.5 text-left text-[11px] leading-tight">{{ eq.pivot.item_note }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 font-bold">
                                        <td colspan="2" class="bg-gray-100 border border-black px-2 py-1.5 tracking-[1em]">小計</td>
                                        <td colspan="4" class="bg-gray-100 border border-black px-2 py-1.5">{{ formatCurrency(equipmentsTotal) }}</td>
                                    </tr>
                                </template>

                                <!-- 材料與工資部分 -->
                                <template v-if="order.materials.length > 0">
                                    <tr v-for="(mat, i) in order.materials" :key="'m'+i">
                                        <td v-if="i === 0" :rowspan="order.materials.length + 1" class="border border-black px-1 py-2 bg-gray-50/30"></td>
                                        
                                        <template v-if="mat.pivot.is_adjustment">
                                            <td colspan="2" class="border border-black px-3 py-1.5 font-bold">{{ mat.pivot.custom_name }}</td>
                                            <td colspan="3" class="border border-black px-3 py-1.5 font-bold">{{ formatCurrency(mat.pivot.unit_price) }}</td>
                                        </template>
                                        <template v-else>
                                            <td class="border border-black px-2 py-1.5">{{ mat.name }}</td>
                                            <td class="border border-black px-2 py-1.5">{{ mat.specs || '　' }}</td>
                                            <td class="border border-black px-1 py-1.5">{{ mat.pivot.quantity }} {{ mat.unit || '組' }}</td>
                                            <td class="border border-black px-2 py-1.5">{{ formatCurrency(mat.pivot.unit_price) }}</td>
                                            <td class="border border-black px-2 py-1.5">{{ formatCurrency(mat.pivot.unit_price * mat.pivot.quantity) }}</td>
                                        </template>
                                        <td class="border border-black px-2 py-1.5 text-left text-[11px] leading-tight">{{ mat.pivot.item_note }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 font-bold">
                                        <td colspan="2" class="bg-gray-100 border border-black px-2 py-1.5 tracking-[1em]">小計</td>
                                        <td colspan="4" class="bg-gray-100 border border-black px-2 py-1.5">{{ formatCurrency(materialsTotal) }}</td>
                                    </tr>
                                </template>

                                <!-- 總計總額 -->
                                <tr class="font-black text-[15px] bg-white">
                                    <td colspan="3" class="border-[1.5px] border-black px-2 py-3 tracking-[2em]">總計</td>
                                    <td colspan="4" class="border-[1.5px] border-black px-2 py-3 text-blue-800">{{ formatCurrency(order.total_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- 頁尾店家資訊 -->
                        <div v-if="shop" class="mt-8 pt-4 border-t-2 border-double border-black grid grid-cols-2 gap-y-1 text-[13px] font-medium leading-relaxed">
                            <div class="col-span-1">服務單位：{{ shop.shop_name }}</div>
                            <div class="col-span-1">負責人：{{ shop.owner_name }}</div>
                            <div class="col-span-1">聯絡電話：{{ shop.phone }}</div>
                            <div class="col-span-1">公司地址：{{ shop.address }}</div>
                            <div class="col-span-1">匯款銀行：{{ shop.bank_name }}</div>
                            <div class="col-span-1">匯款帳號：{{ shop.bank_account }}</div>
                        </div>

                    </div>
                </div>

                <!-- 內部記錄 -->
                <div v-if="order.notes" class="max-w-5xl mx-auto bg-amber-50 p-6 rounded-lg border border-amber-200 print:hidden">
                    <h4 class="font-bold text-amber-800 mb-2">內部管理備註：</h4>
                    <p class="text-amber-900 whitespace-pre-wrap">{{ order.notes }}</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.paper {
    font-family: "Microsoft JhengHei", "PMingLiU", sans-serif;
}

@media print {
    body { background: white; }
    .py-12 { padding: 0 !important; }
    .paper {
        box-shadow: none !important;
        margin: 0 !important;
        width: 100% !important;
        p: 0 !important;
    }
    .print\:hidden { display: none !important; }
}
</style>
