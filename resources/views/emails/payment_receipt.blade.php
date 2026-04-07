<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Pembayaran</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .greeting strong {
            color: #667eea;
        }
        
        .receipt-section {
            margin: 25px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-left: 4px solid #667eea;
            border-radius: 4px;
        }
        
        .receipt-section h3 {
            color: #667eea;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            border-bottom: 1px solid #eee;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            color: #333;
            font-weight: 600;
        }
        
        .payment-details {
            margin: 25px 0;
            padding: 20px;
            background-color: #f0f4ff;
            border-radius: 4px;
        }
        
        .amount-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 2px solid rgba(102, 126, 234, 0.3);
            margin-bottom: 15px;
        }
        
        .amount-label {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }
        
        .amount-value {
            font-size: 24px;
            color: #667eea;
            font-weight: bold;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            font-size: 18px;
        }
        
        .total-label {
            color: #333;
            font-weight: 700;
        }
        
        .total-value {
            color: #667eea;
            font-weight: 700;
            font-size: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            background-color: #10b981;
            color: white;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 15px 0;
        }
        
        .footer {
            padding: 20px 30px;
            background-color: #fafafa;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .divider {
            height: 1px;
            background-color: #eee;
            margin: 20px 0;
        }
        
        .items-table {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .items-table th {
            background-color: #f0f4ff;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            color: #667eea;
            border-bottom: 2px solid #667eea;
        }
        
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>✓ Pembayaran Berhasil</h1>
            <p>Nota Pembayaran {{ $paymentType }}</p>
        </div>
        
        <!-- Status -->
        <div class="content">
            <div class="greeting">
                Halo <strong>{{ $user->name }}</strong>,
            </div>
            
            <p style="margin-bottom: 20px; color: #666;">
                Terima kasih atas pembayaran Anda. Berikut adalah detail nota pembayaran Anda.
            </p>
            
            <div class="status-badge">
                ✓ Pembayaran Berhasil
            </div>
            
            <!-- Informasi Pelanggan -->
            <div class="receipt-section">
                <h3>Informasi Pelanggan</h3>
                <div class="info-row">
                    <span class="info-label">Nama</span>
                    <span class="info-value">{{ $user->name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">{{ $user->email }}</span>
                </div>
            </div>
            
            <!-- Informasi Transaksi -->
            <div class="receipt-section">
                <h3>Detail Transaksi</h3>
                <div class="info-row">
                    <span class="info-label">No. Transaksi</span>
                    <span class="info-value">#{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tipe Pembayaran</span>
                    <span class="info-value">{{ $paymentType }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tanggal Pembayaran</span>
                    <span class="info-value">{{ $paymentDate }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">No. Invoice</span>
                    <span class="info-value">{{ $payment->xendit_invoice_id ?? $payment->xendit_transaction_id ?? 'N/A' }}</span>
                </div>
            </div>
            
            <!-- Detail Pembayaran -->
            <div class="payment-details">
                <div class="amount-section">
                    <span class="amount-label">Jumlah Pembayaran:</span>
                    <span class="amount-value">{{ $formattedAmount }}</span>
                </div>
            </div>
            
            <!-- Informasi Tambahan -->
            @if($paymentType === 'Pemesanan')
                <div class="receipt-section">
                    <h3>Informasi Pemesanan</h3>
                    <div class="info-row">
                        <span class="info-label">Tanggal Mulai</span>
                        <span class="info-value">{{ \Carbon\Carbon::parse($transaction->start_date)->format('d F Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tanggal Akhir</span>
                        <span class="info-value">{{ \Carbon\Carbon::parse($transaction->end_date)->format('d F Y') }}</span>
                    </div>
                </div>
            @endif
            
            <div class="divider"></div>
            
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">
                Jika Anda memiliki pertanyaan atau butuh bantuan, silakan hubungi tim customer support kami.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p style="margin-bottom: 10px;">
                <strong>EnvoRent</strong> - Penyewaan Peralatan Terpercaya
            </p>
            <p style="color: #999; font-size: 11px;">
                Email ini dikirim secara otomatis. Silakan jangan reply email ini.
            </p>
        </div>
    </div>
</body>
</html>
