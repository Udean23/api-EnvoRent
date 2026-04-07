<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\Category;

class AIChatController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $userMessage = $request->message;

        $products = Product::with('category')->get();

        $productData = $products->map(function ($product) {
            return [
                'name' => $product->name,
                'description' => $product->description,
                'price_per_day' => $product->price,
                'formatted_price' => 'Rp' . number_format($product->price, 0, ',', '.'),
                'category' => $product->category->name ?? null,
                'stock' => $product->stock,
                'available' => $product->stock > 0 ? true : false,
            ];
        })->values()->toArray();

        $context = "
You are a friendly and helpful customer service assistant for EnvoRent, a rental platform for outdoor equipment.

Rules:
- Gunakan bahasa Indonesia
- Jawaban harus sopan, jelas, dan membantu
- Berikan rekomendasi produk sesuai kebutuhan user
- Jika user bertanya produk: jelaskan nama, deskripsi, harga, kategori, dan ketersediaan
- Jika user bertanya kategori: jelaskan isi kategori + rekomendasi
- Jika stok habis: sarankan alternatif produk lain
- Gunakan bullet point jika menampilkan lebih dari 1 produk
- Gunakan format harga Rupiah (contoh: Rp50.000/hari)
-tampilkan data yang relevan saja, jangan tampilkan semua data jika tidak diperlukan
- Jangan menyebutkan bahwa kamu adalah AI, fokus pada membantu user dengan informasi produk
-jangan gunakan * dan buat supaya balasanmu mudah dibaca serta tertata rapi
- buat text yang ditampilkan lebih rapi dengan dibagi menggunakan paragraf dan bullet point jika diperlukan


Available products (JSON):
" . json_encode($productData, JSON_PRETTY_PRINT) . "

Customer question:
{$userMessage}

Answer as a helpful customer service.
";

        $response = Http::withOptions(['verify' => false])->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . env('GOOGLE_AI_API_KEY'),
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $context]
                        ]
                    ]
                ]
            ]
        );

        \Log::info('Google AI Response', [
            'status' => $response->status(),
            'body' => $response->body(),
            'json' => $response->json()
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $aiResponse = $data['candidates'][0]['content']['parts'][0]['text'];
            } else {
                $aiResponse = 'Maaf, terjadi kesalahan format respons.';
            }
        } else {
            $aiResponse = 'Maaf, permintaan tidak dapat diproses. Error: ' . $response->status();
        }

        return response()->json([
            'message' => $aiResponse,
            'sender' => 'AI Assistant',
        ]);
    }
}