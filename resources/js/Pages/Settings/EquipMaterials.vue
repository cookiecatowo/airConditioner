<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    brands: Array,
    materials: Array,
    status: String,
});

const activeTab = ref('equipments'); // 'equipments' or 'materials'
const searchQuery = ref('');

// --- 品牌與設備邏輯 ---
const showBrandModal = ref(false);
const editingBrand = ref(null);
const brandForm = useForm({
    name: '',
    phone: '',
});

const openBrandModal = (brand = null) => {
    editingBrand.value = brand;
    if (brand) {
        brandForm.name = brand.name;
        brandForm.phone = brand.phone;
    } else {
        brandForm.reset();
    }
    showBrandModal.value = true;
};

const submitBrand = () => {
    if (editingBrand.value) {
        brandForm.patch(route('brands.update', editingBrand.value.id), {
            onSuccess: () => { showBrandModal.value = false; brandForm.reset(); }
        });
    } else {
        brandForm.post(route('brands.store'), {
            onSuccess: () => { showBrandModal.value = false; brandForm.reset(); }
        });
    }
};

const deleteBrand = (id) => {
    if (confirm('確定要刪除此品牌嗎？這會連同其下的設備型號一併刪除。')) {
        router.delete(route('brands.destroy', id));
    }
};

// --- 設備 (型號) 邏輯 ---
const showEquipModal = ref(false);
const editingEquip = ref(null);
const currentBrandId = ref(null);
const equipForm = useForm({
    brand_id: '',
    model_name: '',
    specs: '',
    default_cost_price: 0,
    default_sale_price: 0,
});

const openEquipModal = (brandId, equip = null) => {
    editingEquip.value = equip;
    currentBrandId.value = brandId;
    if (equip) {
        equipForm.model_name = equip.model_name;
        equipForm.specs = equip.specs;
        equipForm.default_cost_price = equip.default_cost_price;
        equipForm.default_sale_price = equip.default_sale_price;
    } else {
        equipForm.reset();
        equipForm.brand_id = brandId;
    }
    showEquipModal.value = true;
};

const submitEquip = () => {
    if (editingEquip.value) {
        equipForm.patch(route('equipments.update', editingEquip.value.id), {
            onSuccess: () => { showEquipModal.value = false; equipForm.reset(); }
        });
    } else {
        equipForm.post(route('equipments.store'), {
            onSuccess: () => { showEquipModal.value = false; equipForm.reset(); }
        });
    }
};

const deleteEquip = (id) => {
    if (confirm('確定要刪除此型號嗎？')) {
        router.delete(route('equipments.destroy', id));
    }
};

// --- 材料邏輯 ---
const showMaterialModal = ref(false);
const editingMaterial = ref(null);
const materialForm = useForm({
    name: '',
    default_unit_price: 0,
});

const openMaterialModal = (material = null) => {
    editingMaterial.value = material;
    if (material) {
        materialForm.name = material.name;
        materialForm.default_unit_price = material.default_unit_price;
    } else {
        materialForm.reset();
    }
    showMaterialModal.value = true;
};

const submitMaterial = () => {
    if (editingMaterial.value) {
        materialForm.patch(route('materials.update', editingMaterial.value.id), {
            onSuccess: () => { showMaterialModal.value = false; materialForm.reset(); }
        });
    } else {
        materialForm.post(route('materials.store'), {
            onSuccess: () => { showMaterialModal.value = false; materialForm.reset(); }
        });
    }
};

const deleteMaterial = (id) => {
    if (confirm('確定要刪除此材料嗎？')) {
        router.delete(route('materials.destroy', id));
    }
};

// --- 搜尋過濾 ---
const filteredBrands = computed(() => {
    if (!searchQuery.value) return props.brands;
    const query = searchQuery.value.toLowerCase();
    
    return props.brands.map(brand => {
        // 過濾出符合系列名稱或規格的設備
        const matchingEquips = brand.equipments.filter(e => 
            (e.model_name && e.model_name.toLowerCase().includes(query)) || 
            (e.specs && e.specs.toLowerCase().includes(query))
        );
        
        // 如果該品牌下有匹配的設備，則回傳該品牌(僅含匹配設備)
        if (matchingEquips.length > 0) {
            return { ...brand, equipments: matchingEquips };
        }
        return null;
    }).filter(brand => brand !== null);
});

const filteredMaterials = computed(() => {
    if (!searchQuery.value) return props.materials;
    const query = searchQuery.value.toLowerCase();
    return props.materials.filter(m => m.name.toLowerCase().includes(query));
});

// --- 展開/收合邏輯 ---
const expandedBrands = ref([]); // 存儲展開的品牌 ID

const toggleBrand = (brandId) => {
    const index = expandedBrands.value.indexOf(brandId);
    if (index > -1) {
        expandedBrands.value.splice(index, 1);
    } else {
        expandedBrands.value.push(brandId);
    }
};

const isExpanded = (brandId) => expandedBrands.value.includes(brandId);

// 將品牌下的設備按名稱分組
const groupedEquipments = (equipments) => {
    const groups = {};
    equipments.forEach(e => {
        if (!groups[e.model_name]) {
            groups[e.model_name] = [];
        }
        groups[e.model_name].push(e);
    });
    return groups;
};

// 格式化價格顯示
const formatPrice = (price) => {
    if (!price || parseFloat(price) === 0) return '未設定';
    return '$' + price;
};
</script>

<template>
    <Head title="設備與材料管理" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">設備與材料管理</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- 頁籤與搜尋欄 -->
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <div class="flex bg-white rounded-lg p-1 shadow md:w-[320px]">
                        <button 
                            @click="activeTab = 'equipments'"
                            :class="activeTab === 'equipments' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-blue-600'"
                            class="px-6 py-2 rounded-md font-medium transition"
                        >
                            品牌與設備
                        </button>
                        <button 
                            @click="activeTab = 'materials'"
                            :class="activeTab === 'materials' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-blue-600'"
                            class="px-6 py-2 rounded-md font-medium transition"
                        >
                            材料清單
                        </button>
                    </div>
                    <div class="w-full flex items-center mr-2">
                        <div class="w-full m-2">
                            <TextInput 
                                v-model="searchQuery" 
                                type="text" 
                                class="w-full" 
                                placeholder="搜尋名稱/規格..." 
                            />
                        </div>
                        <div>
                            <PrimaryButton class="w-[88px]" v-if="activeTab === 'equipments'" @click="openBrandModal()">
                                新增品牌
                            </PrimaryButton>
                            <PrimaryButton class="w-[88px]" v-else @click="openMaterialModal()">
                                新增材料
                            </PrimaryButton>
                        </div>
                    </div> 
                </div>

                <!-- 品牌與設備內容 -->
                <div v-if="activeTab === 'equipments'" class="grid grid-cols-1 gap-6">
                    <div v-for="brand in filteredBrands" :key="brand.id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-blue-500">
                        <div class="p-6">
                            <!-- 第一行：名稱與按鈕 -->
                            <div class="flex justify-between items-center cursor-pointer" @click="toggleBrand(brand.id)">
                                <div class="flex items-center truncate mr-2">
                                    <svg 
                                        :class="{'rotate-90': isExpanded(brand.id)}"
                                        class="h-5 w-5 text-gray-400 transition-transform mr-2 shrink-0" 
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 truncate">{{ brand.name }}</h3>
                                </div>
                                <div class="flex items-center gap-1 sm:gap-2 shrink-0" @click.stop>
                                    <button @click="openEquipModal(brand.id)" class="bg-white border border-gray-300 text-gray-700 px-2 py-1 rounded text-xs sm:text-sm hover:bg-gray-50 transition">新增設備</button>
                                    <button @click="openBrandModal(brand)" class="bg-white border border-gray-300 text-gray-700 px-2 py-1 rounded text-xs sm:text-sm hover:bg-gray-50 transition">編輯</button>
                                    <button @click="deleteBrand(brand.id)" class="text-red-600 hover:text-red-800 text-xs sm:text-sm px-1">刪除</button>
                                </div>
                            </div>
                            
                            <!-- 第二行：資訊列 (始終顯示，手機不換行) -->
                            <div class="mt-2 ml-7 flex items-center gap-4 text-xs sm:text-sm text-gray-500 cursor-pointer" @click="toggleBrand(brand.id)">
                                <span class="truncate">電話：{{ brand.phone || '未設定' }}</span>
                                <span class="shrink-0">系列數：{{ Object.keys(groupedEquipments(brand.equipments)).length }}</span>
                            </div>

                            <!-- 設備列表 (可摺疊，按系列分組) -->
                            <div v-show="isExpanded(brand.id)" class="mt-6 space-y-8">
                                <div v-for="(equips, modelName) in groupedEquipments(brand.equipments)" :key="modelName" class="bg-gray-50 rounded-lg p-4">
                                    <div class="flex justify-between items-center mb-3 border-b border-gray-200 pb-2">
                                        <h4 class="text-lg font-semibold text-blue-800">{{ modelName }} 系列</h4>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead>
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">規格</th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">預設進價</th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">預設售價</th>
                                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">操作</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-for="equip in equips" :key="equip.id" class="hover:bg-white transition">
                                                    <td class="px-4 py-3 text-sm text-gray-900">{{ equip.specs || '未標註' }}</td>
                                                    <td class="px-4 py-3 text-sm text-right text-gray-900">{{ formatPrice(equip.default_cost_price) }}</td>
                                                    <td class="px-4 py-3 text-sm text-right text-gray-900 font-bold">{{ formatPrice(equip.default_sale_price) }}</td>
                                                    <td class="px-4 py-3 text-sm text-center font-medium">
                                                        <button @click="openEquipModal(brand.id, equip)" class="text-blue-600 hover:text-blue-900 mr-3">編輯</button>
                                                        <button @click="deleteEquip(equip.id)" class="text-red-600 hover:text-red-900">刪除</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div v-if="brand.equipments.length === 0" class="text-center py-4 text-sm text-gray-500 bg-gray-50 rounded">
                                    尚無設備系列與規格資料
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="filteredBrands.length === 0" class="text-center py-12 bg-white rounded-lg shadow">
                        <p class="text-gray-500">找不到符合搜尋條件的設備</p>
                    </div>
                </div>

                <!-- 材料列表內容 -->
                <div v-if="activeTab === 'materials'" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">材料名稱</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">預設單價</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="material in filteredMaterials" :key="material.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ material.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 font-bold">{{ formatPrice(material.default_unit_price) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-medium">
                                        <button @click="openMaterialModal(material)" class="text-blue-600 hover:text-blue-900 mr-3">編輯</button>
                                        <button @click="deleteMaterial(material.id)" class="text-red-600 hover:text-red-900">刪除</button>
                                    </td>
                                </tr>
                                <tr v-if="filteredMaterials.length === 0">
                                    <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">尚無材料資料</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 品牌 Modal -->
        <Modal :show="showBrandModal" @close="showBrandModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">{{ editingBrand ? '編輯品牌' : '新增品牌' }}</h2>
                <form @submit.prevent="submitBrand" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="brand_name" value="品牌名稱" />
                        <TextInput id="brand_name" v-model="brandForm.name" class="mt-1 block w-full" required />
                        <InputError :message="brandForm.errors.name" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="brand_phone" value="品牌電話" />
                        <TextInput id="brand_phone" v-model="brandForm.phone" class="mt-1 block w-full" />
                        <InputError :message="brandForm.errors.phone" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="showBrandModal = false" class="mr-3">取消</SecondaryButton>
                        <PrimaryButton :disabled="brandForm.processing">儲存</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 設備 Modal -->
        <Modal :show="showEquipModal" @close="showEquipModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">{{ editingEquip ? '編輯設備型號' : '新增設備型號' }}</h2>
                <form @submit.prevent="submitEquip" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="equip_name" value="型號/名稱" />
                        <TextInput id="equip_name" v-model="equipForm.model_name" class="mt-1 block w-full" required />
                        <InputError :message="equipForm.errors.model_name" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="equip_specs" value="規格" />
                        <TextInput id="equip_specs" v-model="equipForm.specs" class="mt-1 block w-full" placeholder="例如：3.6KW / 5.0KW" />
                        <InputError :message="equipForm.errors.specs" class="mt-2" />
                    </div>
                    
                    <div class="pt-2 border-t border-gray-100">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">預設金額設定</span>
                            <button type="button" @click="equipForm.default_cost_price = 0; equipForm.default_sale_price = 0" class="text-xs text-red-500 hover:underline">清空進價與售價</button>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="cost_price" value="預設進價" />
                                <TextInput id="cost_price" type="number" v-model="equipForm.default_cost_price" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel for="sale_price" value="預設售價" />
                                <TextInput id="sale_price" type="number" v-model="equipForm.default_sale_price" class="mt-1 block w-full" />
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="showEquipModal = false" class="mr-3">取消</SecondaryButton>
                        <PrimaryButton :disabled="equipForm.processing">儲存</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 材料 Modal -->
        <Modal :show="showMaterialModal" @close="showMaterialModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">{{ editingMaterial ? '編輯材料' : '新增材料' }}</h2>
                <form @submit.prevent="submitMaterial" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="mat_name" value="材料名稱" />
                        <TextInput id="mat_name" v-model="materialForm.name" class="mt-1 block w-full" required />
                        <InputError :message="materialForm.errors.name" class="mt-2" />
                    </div>
                    <div>
                        <div class="flex justify-between items-center">
                            <InputLabel for="mat_price" value="預設單價" />
                            <button type="button" @click="materialForm.default_unit_price = 0" class="text-xs text-gray-400 hover:text-red-500">清空</button>
                        </div>
                        <TextInput id="mat_price" type="number" v-model="materialForm.default_unit_price" class="mt-1 block w-full" />
                        <InputError :message="materialForm.errors.default_unit_price" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="showMaterialModal = false" class="mr-3">取消</SecondaryButton>
                        <PrimaryButton :disabled="materialForm.processing">儲存</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>