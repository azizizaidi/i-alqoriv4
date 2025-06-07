# Setup Auto Update Month Options

## Apa yang telah dibuat:

### 1. Trait HasMonthOptions
- **File**: `app/Traits/HasMonthOptions.php`
- **Fungsi**: Centralized method untuk generate month options
- **Digunakan oleh**: Semua Livewire components dan ReportClassResource

### 2. Artisan Command
- **File**: `app/Console/Commands/UpdateMonthOptions.php`
- **Command**: `php artisan month:update`
- **Fungsi**: Auto generate month options dari Mac 2022 hingga bulan semasa sahaja

### 3. Laravel Scheduler
- **File**: `app/Console/Kernel.php`
- **Schedule**: Setiap bulan pada hari ke-1 jam 00:01
- **Timezone**: Asia/Kuala_Lumpur

### 4. Updated Files
- **ReportClassResource.php**: Guna dynamic method
- **ListMonthlyFee.php**: Guna HasMonthOptions trait
- **ListFee.php**: Guna HasMonthOptions trait
- **ListTransaction.php**: Guna HasMonthOptions trait
- **ListAllowance.php**: Guna HasMonthOptions trait

## Setup di Server (PENTING!)

### 1. Setup Cron Job di Server
Tambah line ini dalam crontab server:

```bash
# Edit crontab
crontab -e

# Tambah line ini (ganti /path/to/your/project dengan path sebenar)
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

### 2. Contoh untuk server anda:
```bash
* * * * * cd /home/azizi/i-alqori/backend/i-alqoriv4 && php artisan schedule:run >> /dev/null 2>&1
```

## Testing

### 1. Test Command Manual
```bash
php artisan month:update
```

### 2. Test Scheduler
```bash
php artisan schedule:list
```

### 3. Test Scheduler Run
```bash
php artisan schedule:run
```

## Cara Kerja

1. **HasMonthOptions trait** provide centralized method untuk semua components
2. **Filter month** hanya tunjuk dari Mac 2022 hingga bulan semasa sahaja
3. **Setiap bulan** (hari ke-1 jam 00:01), sistem akan auto jalankan command
4. **Command akan refresh** month options untuk include bulan baru
5. **Bila masuk bulan baru**, semua filter akan auto dapat bulan tersebut
6. **Tiada perlu** edit code lagi untuk tambah bulan baru

## Files Yang Terkesan

Semua fail ini sekarang auto-update month options:
- `app/Filament/Resources/ReportClassResource.php`
- `app/Livewire/ListMonthlyFee.php`
- `app/Livewire/ListFee.php`
- `app/Livewire/ListTransaction.php`
- `app/Livewire/ListAllowance.php`

## Monitoring

### Check Log Scheduler
```bash
php artisan schedule:list
tail -f storage/logs/laravel.log
```

### Manual Update (jika perlu)
```bash
php artisan month:update
```

## Troubleshooting

### Jika cron tidak jalan:
1. Check crontab: `crontab -l`
2. Check path PHP: `which php`
3. Check permissions: `ls -la artisan`
4. Test manual: `php artisan schedule:run`

### Jika command error:
1. Check logs: `tail -f storage/logs/laravel.log`
2. Test command: `php artisan month:update`
3. Check database connection
