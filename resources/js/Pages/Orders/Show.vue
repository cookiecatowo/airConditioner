<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    order: Object,
    shop: Object,
});

const formatCurrency = (value) => {
    if (value === 0 || value === '0') return '未設定';
    return new Intl.NumberFormat('zh-TW', { minimumFractionDigits: 0 }).format(value);
};

// 快速編輯表單
const quickForm = useForm({
    report_title: props.order.report_title || '估價單',
    notes: props.order.notes || '',
});

const saveQuick = () => {
    quickForm.patch(route('orders.quickEdit', props.order.id));
};

// 設備小計
const equipmentsTotal = computed(() => {
    return props.order.equipments.reduce((sum, e) => sum + (parseFloat(e.pivot.sale_price || 0) * parseInt(e.pivot.quantity || 1)), 0);
});

// 材料小計
const materialsTotal = computed(() => {
    return props.order.materials.reduce((sum, m) => sum + (parseFloat(m.pivot.unit_price || 0) * parseInt(m.pivot.quantity || 1)), 0);
});

// 工作場所照片
const photoInput = ref(null);
const uploading = ref(false);
const uploadError = ref('');

const uploadPhotos = (event) => {
    const files = Array.from(event.target.files || []);
    if (!files.length) return;

    uploading.value = true;
    uploadError.value = '';

    router.post(route('orders.photos.store', props.order.id), { photos: files }, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
            uploadError.value = Object.values(errors)[0] || '上傳失敗，請再試一次。';
        },
        onFinish: () => {
            uploading.value = false;
            if (photoInput.value) photoInput.value.value = '';
        },
    });
};

const saveCaption = (photo, event) => {
    const value = event.target.value;
    if (value === (photo.caption || '')) return;
    router.patch(route('orders.photos.update', photo.id), { caption: value }, { preserveScroll: true });
};

const deletePhoto = (photo) => {
    if (!confirm('確定要刪除這張照片嗎？刪除後無法復原。')) return;
    router.delete(route('orders.photos.destroy', photo.id), { preserveScroll: true });
};
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
                        <div><p class="text-xs text-gray-400">地址</p><p class="font-bold">{{ order.address }}</p></div>
                        <div><p class="text-xs text-gray-400">備註</p><p class="font-bold">{{ order.public_notes }}</p></div>
                        <div>
                            <p class="text-xs text-gray-400">訂單類型</p>
                            <div class="flex gap-2 mt-1 flex-wrap">
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold border"
                                    :class="{ 'bg-blue-100 text-blue-800 border-blue-200': order.type==='install', 'bg-green-100 text-green-800 border-green-200': order.type==='repair', 'bg-purple-100 text-purple-800 border-purple-200': order.type==='maintenance' }">
                                    {{ { install:'安裝', repair:'維修', maintenance:'保養' }[order.type] }}
                                </span>
                                <span v-if="order.work_category" class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="{ 'bg-sky-100 text-sky-700': order.work_category==='ac', 'bg-indigo-100 text-indigo-700': order.work_category==='surveillance', 'bg-gray-100 text-gray-600': order.work_category==='other' }">
                                    {{ { ac:'空調', surveillance:'監視', other:'其他' }[order.work_category] }}
                                </span>
                            </div>
                        </div>
                    </div>

                </div>

                 <!-- 內部備註 (可編輯，僅顯示在網站，不會印出) -->
                <div class="bg-amber-50 p-6 rounded-lg border border-amber-200 print:hidden">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="font-bold text-amber-800">內部管理備註 <span class="text-xs font-normal text-amber-600">（僅顯示於網站，不列印）</span></h4>
                        <div class="flex items-center gap-3">
                            <span v-if="quickForm.recentlySuccessful" class="text-xs text-green-600">已儲存 ✓</span>
                            <button
                                @click="saveQuick"
                                :disabled="quickForm.processing"
                                class="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded transition disabled:opacity-50"
                            >儲存備註</button>
                        </div>
                    </div>
                    <textarea
                        v-model="quickForm.notes"
                        rows="4"
                        placeholder="輸入內部備註（顧客不會看到）..."
                        class="w-full border border-amber-300 rounded-md bg-white p-3 text-amber-900 focus:ring-amber-400 focus:border-amber-400 resize-none"
                    ></textarea>
                    <p v-if="quickForm.errors.notes" class="text-red-500 text-xs mt-1">{{ quickForm.errors.notes }}</p>
                </div>

                <!-- 工作場所照片 (僅顯示於網站，不會出現在報價單) -->
                <div class="bg-white p-6 shadow sm:rounded-lg border-l-4 border-teal-500 print:hidden">
                    <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
                        <h4 class="font-bold text-teal-800">
                            工作場所照片
                            <span class="text-xs font-normal text-teal-600">（僅顯示於網站，不會印在報價單上）</span>
                        </h4>
                        <label
                            class="inline-flex items-center px-4 py-2 bg-teal-600 text-white text-xs font-semibold rounded-md cursor-pointer hover:bg-teal-700 transition"
                            :class="{ 'opacity-50 cursor-not-allowed': uploading }"
                        >
                            {{ uploading ? '上傳中…' : '＋ 新增照片' }}
                            <input
                                ref="photoInput"
                                type="file"
                                accept="image/*"
                                multiple
                                class="hidden"
                                :disabled="uploading"
                                @change="uploadPhotos"
                            />
                        </label>
                    </div>

                    <p v-if="uploadError" class="text-red-500 text-sm mb-3">{{ uploadError }}</p>

                    <p v-if="!order.photos || order.photos.length === 0" class="text-gray-400 text-sm py-6 text-center">
                        還沒有照片。按右上角「＋ 新增照片」上傳，一次可以選多張。
                    </p>

                    <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div v-for="photo in order.photos" :key="photo.id" class="border border-gray-200 rounded-lg overflow-hidden">
                            <a :href="photo.url" target="_blank" class="block bg-gray-100">
                                <img :src="photo.url" alt="" class="w-full h-32 object-cover hover:opacity-90 transition" />
                            </a>
                            <div class="p-2 space-y-2">
                                <input
                                    type="text"
                                    :value="photo.caption"
                                    placeholder="加一行說明…"
                                    maxlength="255"
                                    class="w-full text-xs border-gray-200 rounded px-2 py-1 focus:ring-teal-400 focus:border-teal-400"
                                    @blur="saveCaption(photo, $event)"
                                    @keyup.enter="$event.target.blur()"
                                />
                                <button
                                    type="button"
                                    class="w-full text-xs text-red-500 hover:text-red-700 hover:bg-red-50 rounded py-1 transition"
                                    @click="deletePhoto(photo)"
                                >刪除</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 模擬 A4 報價單區域 -->
                <div class="flex justify-center">
                    <div class="bg-white shadow-2xl p-[1.5cm] w-[210mm] min-h-[297mm] text-black border border-gray-300 relative print:shadow-none print:p-0 print:border-none paper">
                        
                        <!-- 報單標題 (可直接編輯) -->
                        <div class="text-center mb-6 border-b-4 border-double border-black pb-2 print:hidden">
                            <input
                                v-model="quickForm.report_title"
                                class="text-4xl font-bold tracking-[1.2em] text-center w-full border-0 border-b-2 border-dashed border-blue-300 focus:border-blue-500 focus:ring-0 bg-transparent outline-none"
                                @blur="saveQuick"
                            />
                        </div>
                        <!-- 列印時顯示純文字 -->
                        <div class="text-center mb-6 border-b-4 border-double border-black pb-2 hidden print:block">
                            <h1 class="text-4xl font-bold tracking-[1.2em]">{{ quickForm.report_title }}</h1>
                        </div>

                        <!-- 抬頭資訊 (3:2 兩欄佈局) -->
                        <div class="flex mb-6 text-[15px] leading-relaxed">
                            <!-- 左欄 (60%) -->
                            <div class="w-[50%] pr-4 flex flex-col justify-end">
                                <div class="border-b border-black pb-1 flex justify-between items-end mb-4">
                                    <span class="text-2xl">{{ order.customer.name }}</span>
                                    <span class="text-2xl">台照</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="whitespace-nowrap">建單日期：</span>
                                    <span class="flex-grow">{{ order.date }}</span>
                                </div>
                            </div>
                            
                            <!-- 右欄 (40%) -->
                            <div class="w-[50%] text-[13px] space-y-0.5 pl-4">
                                <div class="flex"><span class="w-10 shrink-0">電話：</span><span class="break-all">{{ order.customer.phone }}</span></div>
                                <div class="flex"><span class="w-10 shrink-0">地址：</span><span class="break-all">{{ order.address }}</span></div>
                                <div class="flex"><span class="w-10 shrink-0">備註：</span><span class="break-all">{{ order.public_notes || '　' }}</span></div>
                                <div class="flex"><span class="w-10 shrink-0">統編：</span><span class="break-all">{{ order.tax_id || '　' }}</span></div>
                            </div>
                        </div>

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
                        <div class="mt-4 text-[14px] leading-relaxed">
                            <div class="mb-4">
                                <p>1. 本報價單不含5%營業稅</p>
                                <div class="flex gap-2">
                                    <p class="font-bold">匯款帳戶:</p><p>{{ shop.bank_name }} {{ shop.bank_account_name }} {{ shop.bank_account }}</p>
                                </div>
                            </div>
                            <div class="mt-4 text-right space-y-3">
                                <div class="text-[18px]">
                                    客戶簽章：____________________
                                </div>
                                <div class="text-[14px]">
                                    <span class="font-bold">{{ shop.shop_name }}</span>
                                    &nbsp; TEL:{{ shop.shop_phone }} &nbsp; {{ shop.owner_name }} &nbsp; {{ shop.shop_address }}
                                </div>
                            </div>
                        </div>

                    </div>
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
