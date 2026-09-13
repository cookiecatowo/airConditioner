<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderPhotoController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $request->validate([
            'photos'   => ['required', 'array'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
        ], [
            'photos.*.image' => '只能上傳圖片檔。',
            'photos.*.mimes' => '圖片格式請用 JPG、PNG、WEBP 或 GIF。',
            'photos.*.max'   => '每張照片不能超過 20MB。',
        ]);

        foreach ($request->file('photos') as $file) {
            $order->photos()->create([
                'path' => $file->store("order-photos/{$order->id}", 'public'),
            ]);
        }

        return back();
    }

    public function update(Request $request, OrderPhoto $photo)
    {
        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $photo->update($data);

        return back();
    }

    public function destroy(OrderPhoto $photo)
    {
        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return back();
    }
}
