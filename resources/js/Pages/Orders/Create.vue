<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';

const props = defineProps({
    brands: Array,
});

const form = useForm({
    customer_name: '',
    customer_phone: '',
    customer_tax_id: '',
    address: '',
    public_notes: '',
    date: new Date().toISOString().substr(0, 10),
    type: 'install',
    work_category: 'ac',
    report_title: '估價單',
    equipments: [],
    materials: [],
    notes: '',
});

const commonTitles = ['請款單', '估價單', '報價單'];

// --- 搜尋邏輯 ---
const customerSuggestions = ref([]);
const brandSuggestions = ref([]);
const equipmentSuggestions = ref([]);
const materialSuggestions = ref([]);

const clearSuggestions = () => {
    customerSuggestions.value = [];
    brandSuggestions.value = [];
    equipmentSuggestions.value = [];
    materialSuggestions.value = [];
};

// 點擊外部關閉建議
const handleClickOutside = (e) => {
    if (!e.target.closest('.relative') && !e.target.closest('ul')) {
        clearSuggestions();
    }
};

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

const searchCustomers = async (q) => {
    if (q.length < 1) { customerSuggestions.value = []; return; }
    const res = await axios.get(`/api/search/customers?q=${q}`);
    customerSuggestions.value = res.data;
};

const selectCustomer = (c) => {
    form.customer_name = c.name;
    form.customer_phone = c.phone;
    form.customer_tax_id = c.tax_id || '';
    form.address = c.address;
    customerSuggestions.value = [];
};

const searchBrands = async (index, q) => {
    if (q.length < 1) { brandSuggestions.value = []; return; }
    const res = await axios.get(`/api/search/brands?q=${q}`);
    brandSuggestions.value = res.data.map(b => ({ ...b, targetIndex: index }));
};

const selectBrand = (index, b) => {
    form.equipments[index].brand_id = b.id;
    form.equipments[index].brand_name = b.name;
    if (!form.equipments[index].model_name.startsWith(b.name)) {
        form.equipments[index].model_name = b.name + ' ';
    }
    brandSuggestions.value = [];
    searchEquipments(index, b.name);
};

const searchEquipments = async (index, q) => {
    if (q.length < 1) { equipmentSuggestions.value = []; return; }
    const brandId = form.equipments[index].brand_id;
    const res = await axios.get(`/api/search/equipments?q=${q}&brand_id=${brandId || ''}`);
    equipmentSuggestions.value = res.data.map(e => ({ ...e, targetIndex: index }));
};

const selectEquipment = (index, e) => {
    form.equipments[index].id = e.id;
    form.equipments[index].brand_id = e.brand_id;
    form.equipments[index].brand_name = e.brand?.name || '';
    form.equipments[index].model_name = e.model_name;
    form.equipments[index].specs = e.specs;
    form.equipments[index].cost_price = e.default_cost_price;
    form.equipments[index].sale_price = e.default_sale_price;
    equipmentSuggestions.value = [];
};

const searchMaterials = async (index, q) => {
    if (q.length < 1) { materialSuggestions.value = []; return; }
    const res = await axios.get(`/api/search/materials?q=${q}`);
    materialSuggestions.value = res.data.map(m => ({ ...m, targetIndex: index }));
};

const selectMaterial = (index, m) => {
    form.materials[index].id = m.id;
    form.materials[index].name = m.name;
    form.materials[index].specs = m.specs;
    form.materials[index].unit = m.unit || '組';
    form.materials[index].unit_price = m.default_unit_price;
    materialSuggestions.value = [];
    checkAndAddMaterialRow(index);
};

const checkAndAddMaterialRow = (index) => {
    if (index === form.materials.length - 1) addMaterial();
};

// --- 操作 ---
const addEquipment = () => {
    form.equipments.push({ id: null, brand_id: '', brand_name: '', model_name: '', specs: '', cost_price: 0, sale_price: 0, quantity: 1, unit: '台', item_note: '', is_adjustment: false });
};

const addAdjustmentEquipment = () => {
    form.equipments.push({ id: null, brand_id: null, brand_name: '', model_name: '', specs: '', cost_price: 0, sale_price: 0, quantity: 1, unit: '台', item_note: '', is_adjustment: true });
};

const removeEquipment = (index) => {
    form.equipments.splice(index, 1);
};

const addMaterial = () => {
    form.materials.push({ id: null, name: '', specs: '', unit: '組', unit_price: 0, quantity: 1, item_note: '', is_adjustment: false });
};

const addAdjustmentMaterial = () => {
    form.materials.push({ id: null, name: '', specs: '', unit: '', unit_price: 0, quantity: 1, item_note: '', is_adjustment: true });
};

const removeMaterial = (index) => {
    form.materials.splice(index, 1);
};

// --- 計算可見項目與總額 ---
const visibleEquipments = computed(() => form.equipments.filter(e => e.model_name || e.is_adjustment));
const visibleMaterials = computed(() => form.materials.filter(m => m.name || m.is_adjustment));

const equipmentsTotal = computed(() => visibleEquipments.value.reduce((sum, e) => sum + (parseFloat(e.sale_price || 0) * parseInt(e.quantity || 1)), 0));
const materialsTotal = computed(() => visibleMaterials.value.reduce((sum, m) => sum + (parseFloat(m.unit_price || 0) * parseInt(m.quantity || 1)), 0));
const totalAmount = computed(() => (form.type === 'install' ? equipmentsTotal.value : 0) + materialsTotal.value);

// --- 拖移 ---
const dragItem = ref(null);
const dragOverItem = ref(null);
const onDragStart = (i) => dragItem.value = i;
const onDragEnter = (i) => dragOverItem.value = i;
const onDragEnd = () => {
    if (dragItem.value !== null && dragOverItem.value !== null) {
        const item = form.equipments.splice(dragItem.value, 1)[0];
        form.equipments.splice(dragOverItem.value, 0, item);
    }
    dragItem.value = null; dragOverItem.value = null;
};

const matDragItem = ref(null);
const matDragOverItem = ref(null);
const onMatDragStart = (i) => matDragItem.value = i;
const onMatDragEnter = (i) => matDragOverItem.value = i;
const onMatDragEnd = () => {
    if (matDragItem.value !== null && matDragOverItem.value !== null) {
        const item = form.materials.splice(matDragItem.value, 1)[0];
        form.materials.splice(matDragOverItem.value, 0, item);
    }
    matDragItem.value = null; matDragOverItem.value = null;
};

const submit = () => {
    // 驗證設備品牌
    if (form.type === 'install') {
        for (let i = 0; i < form.equipments.length; i++) {
            const e = form.equipments[i];
            if (!e.is_adjustment && e.model_name) {
                if (!e.brand_name || e.brand_name.trim() === '') {
                    alert(`第 ${i + 1} 項設備未填寫品牌，請確認！`);
                    return;
                }
                // 確保 brand_id 攜帶品牌名稱或 ID 傳回後端
                if (!e.brand_id) {
                    e.brand_id = e.brand_name;
                }
            }
        }
    }
    form.post(route('orders.store'));
};

// 初始化
const defaultMaterials = [
    { id: null, name: '被覆銅管', specs: '2分4分', unit: '米',  unit_price: 500,  quantity: 7,  item_note: '', is_adjustment: false },
    { id: null, name: '被覆銅管', specs: '2分3分', unit: '米',  unit_price: 400,  quantity: 23, item_note: '', is_adjustment: false },
    { id: null, name: '安裝工資', specs: '',        unit: '台',  unit_price: 3500, quantity: 3,  item_note: '', is_adjustment: false },
    { id: null, name: '安裝架',   specs: '',        unit: '組',  unit_price: 1500, quantity: 3,  item_note: '', is_adjustment: false },
    { id: null, name: '控制線電源線',    specs: '', unit: '式',  unit_price: 1500, quantity: 1,  item_note: '', is_adjustment: false },
    { id: null, name: '牆壁挖孔及修補', specs: '', unit: '式',  unit_price: 3000, quantity: 1,  item_note: '', is_adjustment: false },
    { id: null, name: '洗孔',           specs: '', unit: '',    unit_price: 800,  quantity: 1,  item_note: '', is_adjustment: false },
    { id: null, name: '壁掛排水工程',   specs: '打牆及修補', unit: '', unit_price: 1500, quantity: 1, item_note: '', is_adjustment: false },
    { id: null, name: '室內管槽', specs: '百合白',  unit: '台',  unit_price: 3000, quantity: 4,  item_note: '選配', is_adjustment: false },
    { id: null, name: '室外管槽', specs: '',        unit: '式',  unit_price: 5000, quantity: 1,  item_note: '選配', is_adjustment: false },
    { id: null, name: '', specs: '', unit: '組', unit_price: 0, quantity: 1, item_note: '', is_adjustment: false },
];
form.materials = defaultMaterials;
if (form.type === 'install') addEquipment();
watch(() => form.type, (t) => {
    if (t !== 'install') form.equipments = [];
    if (t === 'install' && form.equipments.length === 0) addEquipment();
});
</script>

<template>
    <Head title="新增報價單" />
    <AuthenticatedLayout>
        <template #header><h2 class="font-semibold text-xl text-gray-800 leading-tight">新增報價單</h2></template>
        <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- 標題選擇 -->
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <InputLabel value="報表輸出標題" required />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button v-for="t in commonTitles" :key="t" type="button" @click="form.report_title = t" :class="form.report_title === t ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'" class="px-4 py-2 rounded-md text-sm transition">{{ t }}</button>
                        <TextInput v-model="form.report_title" class="w-48 text-sm" placeholder="自定義標題" />
                    </div>
                </div>
                <!-- 顧客資訊 -->
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <h3 class="text-lg font-bold mb-4 border-b pb-2">顧客與施工資訊</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative">
                            <InputLabel value="顧客姓名" required />
                            <TextInput v-model="form.customer_name" @input="searchCustomers(form.customer_name)" @keydown.enter.prevent class="w-full mt-1" />
                            <ul v-if="customerSuggestions.length > 0" class="absolute z-10 w-full bg-white border rounded shadow-lg mt-1">
                                <li v-for="c in customerSuggestions" :key="c.id" @click="selectCustomer(c)" class="p-2 hover:bg-blue-50 cursor-pointer text-sm">{{ c.name }} - {{ c.phone }}</li>
                            </ul>
                        </div>
                        <div><InputLabel value="聯絡電話" /><TextInput v-model="form.customer_phone" @keydown.enter.prevent class="w-full mt-1" /></div>
                        <div><InputLabel value="統一編號" /><TextInput v-model="form.customer_tax_id" @keydown.enter.prevent class="w-full mt-1" /></div>
                        <div class="md:col-span-2"><InputLabel value="施工地址" required /><TextInput v-model="form.address" @keydown.enter.prevent class="w-full mt-1" /></div>
                        <div class="md:col-span-2"><InputLabel value="報單備註 (顯示)" /><TextInput v-model="form.public_notes" @keydown.enter.prevent class="w-full mt-1" /></div>
                        <div><InputLabel value="建單日期" required /><TextInput type="date" v-model="form.date" @keydown.enter.prevent class="w-full mt-1" /></div>
                        <div>
                            <InputLabel value="訂單類型" required />
                            <div class="mt-2 flex gap-4">
                                <label class="flex items-center"><input type="radio" v-model="form.type" value="install" class="mr-2"> 安裝</label>
                                <label class="flex items-center"><input type="radio" v-model="form.type" value="repair" class="mr-2"> 維修</label>
                                <label class="flex items-center"><input type="radio" v-model="form.type" value="maintenance" class="mr-2"> 保養</label>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="業務分類" />
                            <div class="mt-2 flex gap-4">
                                <label class="flex items-center"><input type="radio" v-model="form.work_category" value="ac" class="mr-2"> 空調</label>
                                <label class="flex items-center"><input type="radio" v-model="form.work_category" value="surveillance" class="mr-2"> 監視</label>
                                <label class="flex items-center"><input type="radio" v-model="form.work_category" value="other" class="mr-2"> 其他</label>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- 設備 -->
                <div v-if="form.type === 'install'" class="bg-white p-6 shadow sm:rounded-lg">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h3 class="text-lg font-bold">設備清單</h3>
                        <div class="flex gap-2">
                            <SecondaryButton @click="addAdjustmentEquipment" class="!bg-amber-50">+ 調整項</SecondaryButton>
                            <SecondaryButton @click="addEquipment">+ 新項目</SecondaryButton>
                        </div>
                    </div>
                    <div v-for="(equip, i) in form.equipments" :key="i" @dragover.prevent @dragenter="onDragEnter(i)" class="mb-4 p-4 bg-gray-50 rounded-lg relative flex gap-4 items-start border border-transparent transition" :class="{'border-blue-300 bg-blue-50/30': dragOverItem === i}">
                        <!-- 拖曳手把 -->
                        <div draggable="true" @dragstart="onDragStart(i)" @dragend="onDragEnd" class="cursor-grab active:cursor-grabbing p-2 text-gray-400 hover:text-blue-500 text-xl select-none">⠿</div>
                        
                        <div class="flex-1 relative">
                            <button @click="removeEquipment(i)" type="button" class="absolute -top-2 -right-2 text-red-400 text-xs hover:text-red-600">移除</button>
                            <div v-if="equip.is_adjustment" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><InputLabel value="名稱" /><TextInput v-model="equip.model_name" class="w-full border-amber-300" /></div>
                                <div><InputLabel value="金額" /><TextInput type="number" v-model="equip.sale_price" class="w-full border-amber-300" /></div>
                                <div><InputLabel value="項目備註" /><TextInput v-model="equip.item_note" class="w-full border-amber-300"/></div>
                            </div>
                            <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="relative"><InputLabel value="品牌" /><TextInput v-model="equip.brand_name" @input="searchBrands(i, equip.brand_name)" @keydown.enter.prevent class="w-full" /><ul v-if="brandSuggestions.length > 0 && brandSuggestions[0].targetIndex === i" class="absolute z-[100] w-full bg-white border rounded shadow-lg mt-1"><li v-for="b in brandSuggestions" :key="b.id" @click="selectBrand(i, b)" class="p-2 hover:bg-blue-50 cursor-pointer text-sm">{{ b.name }}</li></ul></div>
                                <div class="relative"><InputLabel value="型號" /><TextInput v-model="equip.model_name" @input="searchEquipments(i, equip.model_name)" @keydown.enter.prevent class="w-full" /><ul v-if="equipmentSuggestions.length > 0 && equipmentSuggestions[0].targetIndex === i" class="absolute z-[100] w-full bg-white border rounded shadow-lg mt-1"><li v-for="e in equipmentSuggestions" :key="e.id" @click="selectEquipment(i, e)" class="p-2 hover:bg-blue-50 cursor-pointer text-sm">[{{e.brand?.name}}] {{e.model_name}} - {{e.specs}}</li></ul></div>
                                <div class="relative"><InputLabel value="規格" /><TextInput v-model="equip.specs" @input="searchEquipments(i, equip.specs)" @keydown.enter.prevent class="w-full" /><ul v-if="equipmentSuggestions.length > 0 && equipmentSuggestions[0].targetIndex === i" class="absolute z-[100] w-full bg-white border rounded shadow-lg mt-1"><li v-for="e in equipmentSuggestions" :key="e.id" @click="selectEquipment(i, e)" class="p-2 hover:bg-blue-50 cursor-pointer text-sm">[{{e.brand?.name}}] {{e.model_name}} - {{e.specs}}</li></ul></div>
                                <div class="grid grid-cols-2 gap-2"><div><InputLabel value="進價" /><TextInput type="number" v-model="equip.cost_price" @keydown.enter.prevent class="w-full bg-gray-100" /></div><div><InputLabel value="售價" /><TextInput type="number" v-model="equip.sale_price" @keydown.enter.prevent class="w-full border-blue-200" /></div></div>
                                <div class="grid grid-cols-2 gap-2"><div><InputLabel value="數量" /><TextInput type="number" v-model="equip.quantity" @keydown.enter.prevent class="w-full" /></div><div><InputLabel value="單位" /><TextInput v-model="equip.unit" @keydown.enter.prevent class="w-full" placeholder="台" /></div></div>
                                <div><InputLabel value="項目備註" /><TextInput v-model="equip.item_note" @keydown.enter.prevent class="w-full"/></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- 材料 -->
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h3 class="text-lg font-bold">材料與工資細項</h3>
                        <div class="flex gap-2">
                            <button type="button" @click="form.materials = [{ id: null, name: '', specs: '', unit: '組', unit_price: 0, quantity: 1, item_note: '', is_adjustment: false }]" class="px-3 py-1.5 text-xs text-red-600 border border-red-200 rounded hover:bg-red-50 transition">清空</button>
                            <SecondaryButton @click="addAdjustmentMaterial" class="!bg-amber-50">+ 調整項</SecondaryButton>
                            <SecondaryButton @click="addMaterial">+ 新項目</SecondaryButton>
                        </div>
                    </div>
                    <div class="overflow-visible">
                        <table class="min-w-full">
                            <thead><tr class="text-left text-xs text-gray-500 uppercase"><th class="w-8"></th><th>名稱</th><th>規格</th><th class="w-16">單位</th><th class="w-24">單價/金額</th><th class="w-24">數量</th><th>備註</th><th class="w-8"></th></tr></thead>
                            <tbody><tr v-for="(mat, i) in form.materials" :key="i" :class="{'bg-amber-50/50': mat.is_adjustment, 'outline outline-2 outline-blue-400 bg-blue-50/50 z-10 relative': matDragOverItem === i}" @dragover.prevent @dragenter="onMatDragEnter(i)">
                                <td class="py-2 text-center"><div draggable="true" @dragstart="onMatDragStart(i)" @dragend="onMatDragEnd" class="cursor-grab active:cursor-grabbing text-gray-400 hover:text-blue-500 select-none">⠿</div></td>
                                <td class="py-2 pr-2 relative"><TextInput v-model="mat.name" @input="searchMaterials(i, mat.name); checkAndAddMaterialRow(i)" @keydown.enter.prevent class="w-full" :placeholder="mat.is_adjustment ? '調整名稱' : ''" /><ul v-if="!mat.is_adjustment && materialSuggestions.length > 0 && materialSuggestions[0].targetIndex === i" class="absolute z-[100] w-64 bg-white border rounded shadow-lg mt-1"><li v-for="m in materialSuggestions" :key="m.id" @click="selectMaterial(i, m)" class="p-2 hover:bg-blue-50 cursor-pointer text-sm">{{ m.name }} - {{ m.specs }}</li></ul></td>
                                <td class="py-2 pr-2 relative"><TextInput v-model="mat.specs" @input="searchMaterials(i, mat.specs)" @keydown.enter.prevent class="w-full" :disabled="mat.is_adjustment" /><ul v-if="!mat.is_adjustment && materialSuggestions.length > 0 && materialSuggestions[0].targetIndex === i" class="absolute z-[100] w-64 bg-white border rounded shadow-lg mt-1"><li v-for="m in materialSuggestions" :key="m.id" @click="selectMaterial(i, m)" class="p-2 hover:bg-blue-50 cursor-pointer text-sm">{{ m.name }} - {{ m.specs }}</li></ul></td>
                                <td class="py-2 pr-2"><TextInput v-model="mat.unit" @keydown.enter.prevent class="w-full" :disabled="mat.is_adjustment" /></td>
                                <td class="py-2 pr-2"><TextInput type="number" v-model="mat.unit_price" @keydown.enter.prevent class="w-full font-bold" /></td>
                                <td class="py-2 pr-2"><TextInput type="number" v-model="mat.quantity" @keydown.enter.prevent class="w-full" :disabled="mat.is_adjustment" /></td>
                                <td class="py-2 pr-2"><TextInput v-model="mat.item_note" @keydown.enter.prevent class="w-full" placeholder="備註" /></td>
                                <td><button @click="removeMaterial(i)" type="button" class="text-red-500 text-sm">刪</button></td>
                            </tr></tbody>
                        </table>
                    </div>
                </div>

                <!-- 預覽 -->
                <div class="bg-white p-8 shadow sm:rounded-lg overflow-x-auto">
                    <h3 class="text-lg font-bold mb-4 text-gray-700">報表表格預覽</h3>
                    <table class="w-full text-sm border-collapse border border-black text-center">
                        <thead><tr class="bg-gray-50"><th class="border border-black py-1 w-10">項目</th><th class="border border-black py-1 w-1/3">品名</th><th class="border border-black py-1">規格</th><th class="border border-black py-1 w-12">數量</th><th class="border border-black py-1 w-24">單價</th><th class="border border-black py-1 w-24">金額</th><th class="border border-black py-1">備註</th></tr></thead>
                        <tbody>
                            <template v-if="form.type === 'install' && visibleEquipments.length > 0">
                                <tr v-for="(eq, i) in visibleEquipments" :key="'e'+i" class="border border-black">
                                    <td v-if="i===0" :rowspan="visibleEquipments.length + 1" class="border border-black py-1"></td>
                                    <template v-if="eq.is_adjustment"><td colspan="2" class="border border-black px-2 py-1">{{ eq.model_name }}</td><td colspan="3" class="border border-black px-2 py-1 font-bold">{{ eq.sale_price }}</td></template>
                                    <template v-else><td class="border border-black px-2 py-1">{{ eq.model_name }}</td><td class="border border-black py-1">{{ eq.specs }}</td><td class="border border-black py-1">{{ eq.quantity }} {{ eq.unit || '台' }}</td><td class="border border-black px-2 py-1">{{ eq.sale_price }}</td><td class="border border-black px-2 py-1">{{ eq.sale_price * eq.quantity }}</td></template>
                                    <td class="border border-black px-2 py-1">{{ eq.item_note }}</td>
                                </tr>
                                <tr class="border border-black font-bold bg-gray-50/50">
                                    <td colspan="2" class="border border-black py-1 text-center">小計</td>
                                    <td colspan="4" class="border border-black px-2 py-1">{{ equipmentsTotal }}</td>
                                </tr>
                            </template>
                            <template v-if="visibleMaterials.length > 0">
                                <tr v-for="(mat, i) in visibleMaterials" :key="'m'+i" class="border border-black">
                                    <td v-if="i===0" :rowspan="visibleMaterials.length + 1" class="border border-black py-1"></td>
                                    <template v-if="mat.is_adjustment"><td colspan="2" class="border border-black px-2 py-1">{{ mat.name }}</td><td colspan="3" class="border border-black px-2 py-1 font-bold">{{ mat.unit_price }}</td></template>
                                    <template v-else><td class="border border-black px-2 py-1">{{ mat.name }}</td><td class="border border-black py-1">{{ mat.specs }}</td><td class="border border-black py-1">{{ mat.quantity }} {{ mat.unit }}</td><td class="border border-black px-2 py-1">{{ mat.unit_price }}</td><td class="border border-black px-2 py-1">{{ mat.unit_price * mat.quantity }}</td></template>
                                    <td class="border border-black px-2 py-1">{{ mat.item_note }}</td>
                                </tr>
                                <tr class="border border-black font-bold bg-gray-50/50">
                                    <td colspan="2" class="border border-black py-1 text-center">小計</td>
                                    <td colspan="4" class="border border-black px-2 py-1">{{ materialsTotal }}</td>
                                </tr>
                            </template>
                            <tr class="border border-black font-black text-lg bg-blue-50/30">
                                <td colspan="3" class="border border-black py-2 text-center">總計</td>
                                <td colspan="4" class="border border-black px-2 py-2 text-blue-700 font-bold">${{ totalAmount }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 總計 -->
                <div class="bg-white p-6 shadow sm:rounded-lg flex justify-between items-center">
                    <div class="w-2/3"><InputLabel value="後台備註" /><textarea v-model="form.notes" class="w-full mt-1 border-gray-300 rounded-md shadow-sm h-16"></textarea></div>
                    <div class="text-right"><p class="text-sm text-gray-500">預估總金額</p><p class="text-3xl font-black text-blue-600">${{ totalAmount }}</p><PrimaryButton :disabled="form.processing" class="mt-4">建立並產生 Word</PrimaryButton></div>
                </div>
            </form>
        </div></div>
    </AuthenticatedLayout>
</template>