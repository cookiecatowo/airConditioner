<template>
    <div class="p-6 bg-gray-100 min-h-screen">
        <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden md:max-w-2xl p-6">
            <h2 class="text-2xl font-bold mb-4 text-gray-800">大金 Excel 價格表匯入</h2>
            <p class="text-gray-600 mb-6">請上傳整理好的 Excel 檔案，系統將自動計算 0.88 成本並更新資料庫。</p>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                    <input 
                        type="file" 
                        @input="form.excel_file = $event.target.files[0]" 
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        accept=".xlsx, .xls"
                    />
                </div>

                <progress v-if="form.processing" :value="form.progress.percentage" max="100" class="w-full">
                    {{ form.progress.percentage }}%
                </progress>

                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 disabled:opacity-50"
                >
                    {{ form.processing ? '匯入中...' : '開始匯入 (自動計算成本)' }}
                </button>
            </form>

            <div v-if="$page.props.flash.message" class="mt-4 p-4 bg-green-100 text-green-700 rounded-lg">
                {{ $page.props.flash.message }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    excel_file: null,
});

const submit = () => {
    form.post(route('import.daikin.store'), {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            alert('龍哥，大金資料匯入成功了！');
        },
    });
};
</script>