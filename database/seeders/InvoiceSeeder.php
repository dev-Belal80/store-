<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\User;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockMovement;
use App\Models\FinancialTransaction;
use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\TransactionType;
use App\Domain\Store\Enums\UserRole;

class InvoiceSeeder extends Seeder
{
    private int $storeId;
    private int $createdByUserId;
    private array $customers;
    private array $suppliers;
    private array $variants;

    public function run(): void
    {
        $store = Store::withoutGlobalScopes()->where('slug', 'ayad-agro')->first();

        if (!$store) {
            $this->command->error('❌ Run SimulationSeeder first!');
            return;
        }

        $this->storeId  = $store->id;
        $owner = User::withoutGlobalScopes()
            ->where('store_id', $this->storeId)
            ->where('role', UserRole::STORE_OWNER->value)
            ->first();

        if (! $owner) {
            $this->command->error('❌ No store owner found for ayad-agro store.');
            return;
        }

        $this->createdByUserId = (int) $owner->id;

        $this->customers = Customer::withoutGlobalScopes()->where('store_id', $this->storeId)->get()->toArray();
        $this->suppliers = Supplier::withoutGlobalScopes()->where('store_id', $this->storeId)->get()->toArray();
        $this->variants  = ProductVariant::withoutGlobalScopes()->where('store_id', $this->storeId)
                         ->where('is_active', true)
                         ->with(['product' => fn ($query) => $query->withoutGlobalScopes()])
                         ->get()
                         ->toArray();

        $this->command->info('🧾 Creating purchase invoices...');
        $this->seedPurchaseInvoices();

        $this->command->info('🧾 Creating sales invoices...');
        $this->seedSalesInvoices();

        $this->command->info('✅ Invoice Seeder completed!');
        $this->command->info('   Purchase invoices: ' . PurchaseInvoice::withoutGlobalScopes()->where('store_id', $this->storeId)->count());
        $this->command->info('   Sales invoices: '    . SalesInvoice::withoutGlobalScopes()->where('store_id', $this->storeId)->count());
    }

    // ────────────────────────────────────────────────────────────────
    private function seedPurchaseInvoices(): void
    {
        $invoicesData = [
            // فاتورة 1 — مبيدات حشرية كبيرة
            [
                'date'     => now()->subDays(55),
                'supplier' => 'شركة سينجنتا مصر',
                'paid'     => 8500,
                'items'    => [
                    ['product' => 'ديازينون 60%',          'variant' => '1 لتر',   'qty' => 20, 'price' => 140],
                    ['product' => 'كلوربيريفوس 48%',       'variant' => '1 لتر',   'qty' => 15, 'price' => 120],
                    ['product' => 'إيميداكلوبريد 20%',     'variant' => '250 مل',  'qty' => 30, 'price' => 100],
                    ['product' => 'لامبدا سيهالوثرين',     'variant' => '500 مل',  'qty' => 25, 'price' => 95],
                ],
            ],
            // فاتورة 2 — أسمدة كيماوية
            [
                'date'     => now()->subDays(50),
                'supplier' => 'شركة الوادي للأسمدة',
                'paid'     => 12000,
                'items'    => [
                    ['product' => 'نيتروجين يوريا 46%',    'variant' => '25 كجم',  'qty' => 10, 'price' => 400],
                    ['product' => 'نيتروفوسكا 12-12-17',   'variant' => '25 كجم',  'qty' => 8,  'price' => 550],
                    ['product' => 'كالسيوم نيترات',        'variant' => '5 كجم',   'qty' => 20, 'price' => 100],
                    ['product' => 'بوتاسيوم سلفات',        'variant' => '5 كجم',   'qty' => 15, 'price' => 260],
                ],
            ],
            // فاتورة 3 — مبيدات فطرية
            [
                'date'     => now()->subDays(45),
                'supplier' => 'بايير للمبيدات الزراعية',
                'paid'     => 5000,
                'items'    => [
                    ['product' => 'مانكوزيب 80%',          'variant' => '1 كجم',   'qty' => 30, 'price' => 85],
                    ['product' => 'بروبيكونازول 25%',      'variant' => '500 مل',  'qty' => 20, 'price' => 180],
                    ['product' => 'تيبوكونازول 25%',       'variant' => '250 مل',  'qty' => 15, 'price' => 95],
                    ['product' => 'أزوكسيستروبين 25%',    'variant' => '100 مل',  'qty' => 25, 'price' => 85],
                ],
            ],
            // فاتورة 4 — أسمدة عضوية
            [
                'date'     => now()->subDays(42),
                'supplier' => 'مستلزمات النيل الزراعية',
                'paid'     => 3500,
                'items'    => [
                    ['product' => 'هيوميك أسيد',           'variant' => '1 لتر',   'qty' => 20, 'price' => 75],
                    ['product' => 'فولفيك أسيد',           'variant' => '500 مل',  'qty' => 15, 'price' => 55],
                    ['product' => 'أحماض أمينية',          'variant' => '1 لتر',   'qty' => 18, 'price' => 90],
                    ['product' => 'سيويد أعشاب بحرية',    'variant' => '1 لتر',   'qty' => 12, 'price' => 130],
                ],
            ],
            // فاتورة 5 — منظمات نمو
            [
                'date'     => now()->subDays(38),
                'supplier' => 'شركة الدلتا للمبيدات',
                'paid'     => 2800,
                'items'    => [
                    ['product' => 'جبريلين GA3',           'variant' => '50 جم',   'qty' => 10, 'price' => 150],
                    ['product' => 'سيتوكينين',             'variant' => '100 مل',  'qty' => 15, 'price' => 55],
                    ['product' => 'إيثيفون 48%',           'variant' => '500 مل',  'qty' => 12, 'price' => 130],
                    ['product' => 'حمض الإندول أسيتيك',   'variant' => '100 جم',  'qty' => 10, 'price' => 85],
                ],
            ],
            // فاتورة 6 — مبيدات أعشاب
            [
                'date'     => now()->subDays(35),
                'supplier' => 'الشركة العربية للزراعة',
                'paid'     => 4200,
                'items'    => [
                    ['product' => 'جلايفوسيت 48%',         'variant' => '5 لتر',   'qty' => 8,  'price' => 250],
                    ['product' => 'أتروزين 50%',           'variant' => '1 كجم',   'qty' => 20, 'price' => 70],
                    ['product' => 'بندميثالين 33%',        'variant' => '1 لتر',   'qty' => 15, 'price' => 85],
                    ['product' => 'هالوكسيفوب 10.8%',     'variant' => '500 مل',  'qty' => 18, 'price' => 75],
                ],
            ],
            // فاتورة 7 — مطهرات تربة
            [
                'date'     => now()->subDays(30),
                'supplier' => 'مستودعات السلام الزراعية',
                'paid'     => 3000,
                'items'    => [
                    ['product' => 'دازوميت 98%',           'variant' => '1 كجم',   'qty' => 10, 'price' => 140],
                    ['product' => 'تريكودرما',             'variant' => '500 جم',  'qty' => 15, 'price' => 170],
                    ['product' => 'مانكوزيب 80%',          'variant' => '5 كجم',   'qty' => 5,  'price' => 390],
                ],
            ],
            // فاتورة 8 — معدات
            [
                'date'     => now()->subDays(28),
                'supplier' => 'شركة فرتيلايزر مصر',
                'paid'     => 5500,
                'items'    => [
                    ['product' => 'رشاشة ظهرية يدوية',    'variant' => '16 لتر',  'qty' => 10, 'price' => 180],
                    ['product' => 'رشاشة كهربائية',       'variant' => '16 لتر',  'qty' => 5,  'price' => 480],
                    ['product' => 'قفازات مطاطية',        'variant' => 'مقاس M',  'qty' => 50, 'price' => 12],
                    ['product' => 'كمامة واقية',           'variant' => 'قطعة',    'qty' => 30, 'price' => 25],
                ],
            ],
            // فاتورة 9 — تجديد مبيدات حشرية
            [
                'date'     => now()->subDays(22),
                'supplier' => 'شركة سينجنتا مصر',
                'paid'     => 6000,
                'items'    => [
                    ['product' => 'أبامكتين 1.8%',        'variant' => '250 مل',  'qty' => 20, 'price' => 130],
                    ['product' => 'تيامثوكسام 25%',       'variant' => '250 جم',  'qty' => 15, 'price' => 120],
                    ['product' => 'سبينوساد 24%',         'variant' => '100 مل',  'qty' => 25, 'price' => 80],
                    ['product' => 'إندوكساكارب 15%',      'variant' => '250 مل',  'qty' => 12, 'price' => 150],
                ],
            ],
            // فاتورة 10 — أسمدة متنوعة
            [
                'date'     => now()->subDays(18),
                'supplier' => 'شركة الوادي للأسمدة',
                'paid'     => 4800,
                'items'    => [
                    ['product' => 'مونوبوتاسيوم فوسفات',  'variant' => '5 كجم',   'qty' => 10, 'price' => 300],
                    ['product' => 'سلفات الحديد',         'variant' => '1 كجم',   'qty' => 20, 'price' => 38],
                    ['product' => 'نيتروجين يوريا 46%',   'variant' => '50 كجم',  'qty' => 3,  'price' => 780],
                    ['product' => 'كالسيوم نيترات',       'variant' => '25 كجم',  'qty' => 5,  'price' => 480],
                ],
            ],
            // فاتورة 11
            [
                'date'     => now()->subDays(15),
                'supplier' => 'بايير للمبيدات الزراعية',
                'paid'     => 3200,
                'items'    => [
                    ['product' => 'كوبروكسات',             'variant' => '1 لتر',   'qty' => 15, 'price' => 100],
                    ['product' => 'مانكوزيب 80%',          'variant' => '200 جم',  'qty' => 50, 'price' => 20],
                    ['product' => 'بروبيكونازول 25%',      'variant' => '100 مل',  'qty' => 30, 'price' => 40],
                ],
            ],
            // فاتورة 12
            [
                'date'     => now()->subDays(12),
                'supplier' => 'مستلزمات النيل الزراعية',
                'paid'     => 2500,
                'items'    => [
                    ['product' => 'هيوميك أسيد',           'variant' => '5 لتر',   'qty' => 5,  'price' => 340],
                    ['product' => 'أحماض أمينية',          'variant' => '500 مل',  'qty' => 20, 'price' => 48],
                    ['product' => 'سيويد أعشاب بحرية',    'variant' => '250 مل',  'qty' => 15, 'price' => 38],
                ],
            ],
            // فاتورة 13
            [
                'date'     => now()->subDays(10),
                'supplier' => 'الشركة العربية للزراعة',
                'paid'     => 7000,
                'items'    => [
                    ['product' => 'جلايفوسيت 48%',         'variant' => '20 لتر',  'qty' => 5,  'price' => 950],
                    ['product' => 'بندميثالين 33%',        'variant' => '5 لتر',   'qty' => 8,  'price' => 390],
                ],
            ],
            // فاتورة 14
            [
                'date'     => now()->subDays(8),
                'supplier' => 'شركة فرتيلايزر مصر',
                'paid'     => 4000,
                'items'    => [
                    ['product' => 'نيتروفوسكا 12-12-17',   'variant' => '5 كجم',   'qty' => 20, 'price' => 115],
                    ['product' => 'بوتاسيوم سلفات',        'variant' => '1 كجم',   'qty' => 30, 'price' => 55],
                    ['product' => 'مونوبوتاسيوم فوسفات',   'variant' => '1 كجم',   'qty' => 25, 'price' => 65],
                ],
            ],
            // فاتورة 15
            [
                'date'     => now()->subDays(6),
                'supplier' => 'شركة الدلتا للمبيدات',
                'paid'     => 5500,
                'items'    => [
                    ['product' => 'ديازينون 60%',          'variant' => '500 مل',  'qty' => 30, 'price' => 75],
                    ['product' => 'إيميداكلوبريد 20%',     'variant' => '100 مل',  'qty' => 40, 'price' => 45],
                    ['product' => 'أبامكتين 1.8%',         'variant' => '50 مل',   'qty' => 50, 'price' => 30],
                ],
            ],
            // فاتورة 16
            [
                'date'     => now()->subDays(5),
                'supplier' => 'مستودعات السلام الزراعية',
                'paid'     => 2800,
                'items'    => [
                    ['product' => 'تريكودرما',             'variant' => '1 كجم',   'qty' => 8,  'price' => 320],
                    ['product' => 'دازوميت 98%',           'variant' => '500 جم',  'qty' => 10, 'price' => 75],
                ],
            ],
            // فاتورة 17
            [
                'date'     => now()->subDays(4),
                'supplier' => 'شركة سينجنتا مصر',
                'paid'     => 3600,
                'items'    => [
                    ['product' => 'سبينوساد 24%',          'variant' => '500 مل',  'qty' => 8,  'price' => 350],
                    ['product' => 'تيامثوكسام 25%',        'variant' => '1 كجم',   'qty' => 5,  'price' => 440],
                ],
            ],
            // فاتورة 18
            [
                'date'     => now()->subDays(3),
                'supplier' => 'بايير للمبيدات الزراعية',
                'paid'     => 4200,
                'items'    => [
                    ['product' => 'أزوكسيستروبين 25%',    'variant' => '250 مل',  'qty' => 10, 'price' => 195],
                    ['product' => 'تيبوكونازول 25%',       'variant' => '1 لتر',   'qty' => 5,  'price' => 350],
                    ['product' => 'كوبروكسات',             'variant' => '250 مل',  'qty' => 20, 'price' => 28],
                ],
            ],
            // فاتورة 19
            [
                'date'     => now()->subDays(2),
                'supplier' => 'شركة الوادي للأسمدة',
                'paid'     => 3100,
                'items'    => [
                    ['product' => 'جبريلين GA3',           'variant' => '10 جم',   'qty' => 20, 'price' => 35],
                    ['product' => 'إيثيفون 48%',           'variant' => '100 مل',  'qty' => 25, 'price' => 30],
                    ['product' => 'حمض الإندول أسيتيك',   'variant' => '50 جم',   'qty' => 15, 'price' => 45],
                    ['product' => 'سيتوكينين',             'variant' => '500 مل',  'qty' => 8,  'price' => 240],
                ],
            ],
            // فاتورة 20
            [
                'date'     => now()->subDays(1),
                'supplier' => 'مستلزمات النيل الزراعية',
                'paid'     => 2000,
                'items'    => [
                    ['product' => 'رشاشة ظهرية يدوية',    'variant' => '20 لتر',  'qty' => 5,  'price' => 220],
                    ['product' => 'قفازات مطاطية',        'variant' => 'مقاس L',  'qty' => 30, 'price' => 12],
                    ['product' => 'كمامة واقية',           'variant' => 'علبة 10', 'qty' => 3,  'price' => 220],
                ],
            ],
        ];

        foreach ($invoicesData as $i => $data) {
            $this->createPurchaseInvoice($data, $i + 1);
        }
    }

    // ────────────────────────────────────────────────────────────────
    private function seedSalesInvoices(): void
    {
        $invoicesData = [
            // فاتورة بيع 1
            [
                'date'     => now()->subDays(52),
                'customer' => 'محمد عبد الرحمن',
                'paid'     => 2500,
                'discount' => 0,
                'items'    => [
                    ['product' => 'ديازينون 60%',          'variant' => '500 مل',  'qty' => 5,  'price' => 110],
                    ['product' => 'مانكوزيب 80%',          'variant' => '1 كجم',   'qty' => 8,  'price' => 130],
                    ['product' => 'هيوميك أسيد',           'variant' => '1 لتر',   'qty' => 4,  'price' => 115],
                ],
            ],
            // فاتورة بيع 2
            [
                'date'     => now()->subDays(48),
                'customer' => 'أحمد السيد إبراهيم',
                'paid'     => 8000,
                'discount' => 500,
                'items'    => [
                    ['product' => 'نيتروجين يوريا 46%',    'variant' => '25 كجم',  'qty' => 5,  'price' => 600],
                    ['product' => 'نيتروفوسكا 12-12-17',   'variant' => '25 كجم',  'qty' => 4,  'price' => 820],
                    ['product' => 'كالسيوم نيترات',        'variant' => '5 كجم',   'qty' => 10, 'price' => 155],
                    ['product' => 'بوتاسيوم سلفات',        'variant' => '1 كجم',   'qty' => 8,  'price' => 85],
                ],
            ],
            // فاتورة بيع 3
            [
                'date'     => now()->subDays(44),
                'customer' => 'خالد فتحي حسين',
                'paid'     => 3200,
                'discount' => 200,
                'items'    => [
                    ['product' => 'إيميداكلوبريد 20%',     'variant' => '100 مل',  'qty' => 10, 'price' => 70],
                    ['product' => 'أبامكتين 1.8%',         'variant' => '250 مل',  'qty' => 8,  'price' => 200],
                    ['product' => 'بروبيكونازول 25%',      'variant' => '500 مل',  'qty' => 5,  'price' => 275],
                ],
            ],
            // فاتورة بيع 4
            [
                'date'     => now()->subDays(40),
                'customer' => 'وليد عصام الدين',
                'paid'     => 12000,
                'discount' => 1000,
                'items'    => [
                    ['product' => 'جلايفوسيت 48%',         'variant' => '5 لتر',   'qty' => 10, 'price' => 380],
                    ['product' => 'أتروزين 50%',           'variant' => '1 كجم',   'qty' => 15, 'price' => 108],
                    ['product' => 'بندميثالين 33%',        'variant' => '5 لتر',   'qty' => 6,  'price' => 590],
                    ['product' => 'هالوكسيفوب 10.8%',     'variant' => '1 لتر',   'qty' => 8,  'price' => 215],
                ],
            ],
            // فاتورة بيع 5
            [
                'date'     => now()->subDays(36),
                'customer' => 'طارق رمضان عوض',
                'paid'     => 4500,
                'discount' => 0,
                'items'    => [
                    ['product' => 'هيوميك أسيد',           'variant' => '5 لتر',   'qty' => 3,  'price' => 510],
                    ['product' => 'أحماض أمينية',          'variant' => '1 لتر',   'qty' => 8,  'price' => 138],
                    ['product' => 'سيويد أعشاب بحرية',    'variant' => '1 لتر',   'qty' => 6,  'price' => 200],
                    ['product' => 'فولفيك أسيد',           'variant' => '500 مل',  'qty' => 5,  'price' => 85],
                ],
            ],
            // فاتورة بيع 6
            [
                'date'     => now()->subDays(33),
                'customer' => 'رامي وليد الشرقاوي',
                'paid'     => 5000,
                'discount' => 300,
                'items'    => [
                    ['product' => 'سبينوساد 24%',          'variant' => '500 مل',  'qty' => 5,  'price' => 530],
                    ['product' => 'تيامثوكسام 25%',        'variant' => '250 جم',  'qty' => 8,  'price' => 185],
                    ['product' => 'إندوكساكارب 15%',       'variant' => '250 مل',  'qty' => 6,  'price' => 230],
                ],
            ],
            // فاتورة بيع 7
            [
                'date'     => now()->subDays(29),
                'customer' => 'ماجد توفيق الزيات',
                'paid'     => 3800,
                'discount' => 0,
                'items'    => [
                    ['product' => 'مانكوزيب 80%',          'variant' => '5 كجم',   'qty' => 4,  'price' => 580],
                    ['product' => 'تيبوكونازول 25%',       'variant' => '250 مل',  'qty' => 6,  'price' => 148],
                    ['product' => 'أزوكسيستروبين 25%',    'variant' => '100 مل',  'qty' => 8,  'price' => 132],
                ],
            ],
            // فاتورة بيع 8
            [
                'date'     => now()->subDays(25),
                'customer' => 'يوسف سامي النجار',
                'paid'     => 2200,
                'discount' => 0,
                'items'    => [
                    ['product' => 'جبريلين GA3',           'variant' => '50 جم',   'qty' => 5,  'price' => 230],
                    ['product' => 'سيتوكينين',             'variant' => '100 مل',  'qty' => 8,  'price' => 85],
                    ['product' => 'إيثيفون 48%',           'variant' => '100 مل',  'qty' => 10, 'price' => 47],
                ],
            ],
            // فاتورة بيع 9
            [
                'date'     => now()->subDays(21),
                'customer' => 'حسن محمود علي',
                'paid'     => 1800,
                'discount' => 0,
                'items'    => [
                    ['product' => 'كلوربيريفوس 48%',       'variant' => '250 مل',  'qty' => 10, 'price' => 55],
                    ['product' => 'لامبدا سيهالوثرين',     'variant' => '100 مل',  'qty' => 15, 'price' => 35],
                    ['product' => 'ديازينون 60%',          'variant' => '100 مل',  'qty' => 20, 'price' => 28],
                ],
            ],
            // فاتورة بيع 10
            [
                'date'     => now()->subDays(17),
                'customer' => 'عبد العزيز محمد ربيع',
                'paid'     => 6500,
                'discount' => 500,
                'items'    => [
                    ['product' => 'نيتروجين يوريا 46%',    'variant' => '50 كجم',  'qty' => 3,  'price' => 1150],
                    ['product' => 'مونوبوتاسيوم فوسفات',   'variant' => '5 كجم',   'qty' => 5,  'price' => 460],
                    ['product' => 'سلفات الحديد',          'variant' => '1 كجم',   'qty' => 10, 'price' => 58],
                ],
            ],
            // فاتورة بيع 11
            [
                'date'     => now()->subDays(14),
                'customer' => 'عادل فريد منصور',
                'paid'     => 2900,
                'discount' => 0,
                'items'    => [
                    ['product' => 'دازوميت 98%',           'variant' => '1 كجم',   'qty' => 5,  'price' => 216],
                    ['product' => 'تريكودرما',             'variant' => '500 جم',  'qty' => 8,  'price' => 265],
                ],
            ],
            // فاتورة بيع 12
            [
                'date'     => now()->subDays(11),
                'customer' => 'إبراهيم مصطفى سالم',
                'paid'     => 1500,
                'discount' => 0,
                'items'    => [
                    ['product' => 'رشاشة ظهرية يدوية',    'variant' => '16 لتر',  'qty' => 3,  'price' => 280],
                    ['product' => 'قفازات مطاطية',        'variant' => 'مقاس M',  'qty' => 20, 'price' => 20],
                    ['product' => 'كمامة واقية',           'variant' => 'قطعة',    'qty' => 15, 'price' => 40],
                ],
            ],
            // فاتورة بيع 13
            [
                'date'     => now()->subDays(9),
                'customer' => 'محمود أحمد الجمال',
                'paid'     => 4100,
                'discount' => 200,
                'items'    => [
                    ['product' => 'كوبروكسات',             'variant' => '1 لتر',   'qty' => 8,  'price' => 155],
                    ['product' => 'بروبيكونازول 25%',      'variant' => '100 مل',  'qty' => 15, 'price' => 62],
                    ['product' => 'مانكوزيب 80%',          'variant' => '200 جم',  'qty' => 30, 'price' => 32],
                ],
            ],
            // فاتورة بيع 14
            [
                'date'     => now()->subDays(7),
                'customer' => 'سامي جلال عثمان',
                'paid'     => 3300,
                'discount' => 0,
                'items'    => [
                    ['product' => 'أبامكتين 1.8%',         'variant' => '50 مل',   'qty' => 20, 'price' => 48],
                    ['product' => 'إيميداكلوبريد 20%',     'variant' => '250 مل',  'qty' => 10, 'price' => 155],
                    ['product' => 'تيامثوكسام 25%',        'variant' => '100 جم',  'qty' => 15, 'price' => 85],
                ],
            ],
            // فاتورة بيع 15
            [
                'date'     => now()->subDays(5),
                'customer' => 'هشام نبيل السيد',
                'paid'     => 2700,
                'discount' => 0,
                'items'    => [
                    ['product' => 'جلايفوسيت 48%',         'variant' => '1 لتر',   'qty' => 10, 'price' => 85],
                    ['product' => 'هالوكسيفوب 10.8%',     'variant' => '500 مل',  'qty' => 8,  'price' => 115],
                    ['product' => 'أتروزين 50%',           'variant' => '500 جم',  'qty' => 15, 'price' => 60],
                ],
            ],
            // فاتورة بيع 16
            [
                'date'     => now()->subDays(4),
                'customer' => 'عمر عبد الله محمد',
                'paid'     => 5800,
                'discount' => 800,
                'items'    => [
                    ['product' => 'نيتروفوسكا 12-12-17',   'variant' => '25 كجم',  'qty' => 3,  'price' => 820],
                    ['product' => 'كالسيوم نيترات',        'variant' => '25 كجم',  'qty' => 3,  'price' => 720],
                    ['product' => 'بوتاسيوم سلفات',        'variant' => '5 كجم',   'qty' => 4,  'price' => 395],
                ],
            ],
            // فاتورة بيع 17
            [
                'date'     => now()->subDays(3),
                'customer' => 'تامر عبد الفتاح راضي',
                'paid'     => 2100,
                'discount' => 0,
                'items'    => [
                    ['product' => 'هيوميك أسيد',           'variant' => '500 مل',  'qty' => 10, 'price' => 62],
                    ['product' => 'فولفيك أسيد',           'variant' => '1 لتر',   'qty' => 6,  'price' => 155],
                    ['product' => 'أحماض أمينية',          'variant' => '500 مل',  'qty' => 8,  'price' => 74],
                ],
            ],
            // فاتورة بيع 18
            [
                'date'     => now()->subDays(2),
                'customer' => 'كريم صلاح عبد الحميد',
                'paid'     => 3500,
                'discount' => 0,
                'items'    => [
                    ['product' => 'رشاشة كهربائية',        'variant' => '16 لتر',  'qty' => 3,  'price' => 720],
                    ['product' => 'قفازات مطاطية',        'variant' => 'مقاس S',  'qty' => 25, 'price' => 20],
                    ['product' => 'كمامة واقية',           'variant' => 'علبة 10', 'qty' => 2,  'price' => 340],
                ],
            ],
            // فاتورة بيع 19
            [
                'date'     => now()->subDays(1),
                'customer' => 'باسم جورج حنا',
                'paid'     => 1800,
                'discount' => 0,
                'items'    => [
                    ['product' => 'سبينوساد 24%',          'variant' => '100 مل',  'qty' => 6,  'price' => 125],
                    ['product' => 'إندوكساكارب 15%',       'variant' => '100 مل',  'qty' => 8,  'price' => 100],
                    ['product' => 'لامبدا سيهالوثرين',     'variant' => '500 مل',  'qty' => 5,  'price' => 145],
                ],
            ],
            // فاتورة بيع 20
            [
                'date'     => now(),
                'customer' => 'علي حسن الصعيدي',
                'paid'     => 4200,
                'discount' => 300,
                'items'    => [
                    ['product' => 'تريكودرما',             'variant' => '1 كجم',   'qty' => 5,  'price' => 495],
                    ['product' => 'دازوميت 98%',           'variant' => '500 جم',  'qty' => 8,  'price' => 116],
                    ['product' => 'مانكوزيب 80%',          'variant' => '1 كجم',   'qty' => 10, 'price' => 130],
                    ['product' => 'أزوكسيستروبين 25%',    'variant' => '250 مل',  'qty' => 4,  'price' => 300],
                ],
            ],
        ];

        foreach ($invoicesData as $i => $data) {
            $this->createSalesInvoice($data, $i + 1);
        }
    }

    // ────────────────────────────────────────────────────────────────
    private function createPurchaseInvoice(array $data, int $number): void
    {
        $supplier = collect($this->suppliers)
            ->firstWhere('name', $data['supplier']);

        if (!$supplier) return;

        $invoiceNumber = 'PI-' . date('Y') . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);

        if (PurchaseInvoice::withoutGlobalScopes()
            ->where('store_id', $this->storeId)
            ->where('invoice_number', $invoiceNumber)
            ->exists()) {
            return;
        }

        $totalAmount   = 0;
        $itemsToCreate = [];

        foreach ($data['items'] as $itemData) {
            $variant = collect($this->variants)->first(function ($v) use ($itemData) {
                return $v['product']['name'] === $itemData['product']
                    && $v['name'] === $itemData['variant'];
            });

            if (!$variant) continue;

            $lineTotal     = $itemData['qty'] * $itemData['price'];
            $totalAmount  += $lineTotal;

            $itemsToCreate[] = [
                'product_id'   => $variant['product_id'],
                'variant_id'   => $variant['id'],
                'product_name' => $variant['product']['name'],
                'variant_name' => $variant['name'],
                'ordered_quantity' => $itemData['qty'],
                'received_quantity' => $itemData['qty'],
                'unit_price'   => $itemData['price'],
                'total_price'  => $lineTotal,
            ];
        }

        $paidAmount      = min($data['paid'], $totalAmount);
        $remainingAmount = $totalAmount - $paidAmount;

        $invoice = PurchaseInvoice::create([
            'store_id'         => $this->storeId,
            'supplier_id'      => $supplier['id'],
            'invoice_number'   => $invoiceNumber,
            'total_amount'     => $totalAmount,
            'paid_amount'      => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'status'           => 'confirmed',
            'notes'            => null,
            'created_by'       => $this->createdByUserId,
            'created_at'       => $data['date'],
            'updated_at'       => $data['date'],
        ]);

        foreach ($itemsToCreate as $item) {
            PurchaseInvoiceItem::create(array_merge(
                $item,
                [
                    'invoice_id' => $invoice->id,
                ]
            ));

            StockMovement::create([
                'store_id'       => $this->storeId,
                'product_id'     => $item['product_id'],
                'variant_id'     => $item['variant_id'],
                'type'           => 'in',
                'quantity'       => $item['received_quantity'],
                'reference_type' => 'purchase_invoice',
                'reference_id'   => $invoice->id,
                'notes'          => "Seed purchase invoice {$invoiceNumber}",
                'created_by'     => $this->createdByUserId,
                'created_at'     => $data['date'],
                'updated_at'     => $data['date'],
            ]);
        }

        FinancialTransaction::create([
            'store_id'       => $this->storeId,
            'party_type'     => PartyType::SUPPLIER,
            'party_id'       => $supplier['id'],
            'type'           => TransactionType::DEBIT,
            'amount'         => $totalAmount,
            'reference_type' => 'purchase_invoice',
            'reference_id'   => $invoice->id,
            'description'    => "فاتورة شراء - {$invoiceNumber}",
            'created_by'     => $this->createdByUserId,
            'created_at'     => $data['date'],
            'updated_at'     => $data['date'],
        ]);

        if ($paidAmount > 0) {
            FinancialTransaction::create([
                'store_id'       => $this->storeId,
                'party_type'     => PartyType::SUPPLIER,
                'party_id'       => $supplier['id'],
                'type'           => TransactionType::CREDIT,
                'amount'         => $paidAmount,
                'reference_type' => 'purchase_invoice_payment',
                'reference_id'   => $invoice->id,
                'description'    => "دفعة شراء - {$invoiceNumber}",
                'created_by'     => $this->createdByUserId,
                'created_at'     => $data['date'],
                'updated_at'     => $data['date'],
            ]);
        }
    }

    // ────────────────────────────────────────────────────────────────
    private function createSalesInvoice(array $data, int $number): void
    {
        $customer = collect($this->customers)
            ->firstWhere('name', $data['customer']);

        if (!$customer) return;

        $invoiceNumber = 'SI-' . date('Y') . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);

        if (SalesInvoice::withoutGlobalScopes()
            ->where('store_id', $this->storeId)
            ->where('invoice_number', $invoiceNumber)
            ->exists()) {
            return;
        }

        $totalAmount   = 0;
        $itemsToCreate = [];

        foreach ($data['items'] as $itemData) {
            $variant = collect($this->variants)->first(function ($v) use ($itemData) {
                return $v['product']['name'] === $itemData['product']
                    && $v['name'] === $itemData['variant'];
            });

            if (!$variant) continue;

            $lineTotal     = $itemData['qty'] * $itemData['price'];
            $totalAmount  += $lineTotal;

            $itemsToCreate[] = [
                'product_id'   => $variant['product_id'],
                'variant_id'   => $variant['id'],
                'product_name' => $variant['product']['name'],
                'variant_name' => $variant['name'],
                'quantity'     => $itemData['qty'],
                'unit_price'   => $itemData['price'],
                'total_price'  => $lineTotal,
            ];
        }

        $discountAmount  = $data['discount'] ?? 0;
        $netAmount       = max($totalAmount - $discountAmount, 0);
        $paidAmount      = min($data['paid'], $netAmount);
        $remainingAmount = $netAmount - $paidAmount;

        $invoice = SalesInvoice::create([
            'store_id'         => $this->storeId,
            'customer_id'      => $customer['id'],
            'invoice_number'   => $invoiceNumber,
            'total_amount'     => $totalAmount,
            'discount_amount'  => $discountAmount,
            'net_amount'       => $netAmount,
            'paid_amount'      => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'status'           => 'confirmed',
            'notes'            => null,
            'created_by'       => $this->createdByUserId,
            'created_at'       => $data['date'],
            'updated_at'       => $data['date'],
        ]);

        foreach ($itemsToCreate as $item) {
            SalesInvoiceItem::create(array_merge(
                $item,
                ['invoice_id' => $invoice->id]
            ));

            StockMovement::create([
                'store_id'       => $this->storeId,
                'product_id'     => $item['product_id'],
                'variant_id'     => $item['variant_id'],
                'type'           => 'out',
                'quantity'       => $item['quantity'],
                'reference_type' => 'sales_invoice',
                'reference_id'   => $invoice->id,
                'notes'          => "Seed sales invoice {$invoiceNumber}",
                'created_by'     => $this->createdByUserId,
                'created_at'     => $data['date'],
                'updated_at'     => $data['date'],
            ]);
        }

        FinancialTransaction::create([
            'store_id'       => $this->storeId,
            'party_type'     => PartyType::CUSTOMER,
            'party_id'       => $customer['id'],
            'type'           => TransactionType::DEBIT,
            'amount'         => $netAmount,
            'reference_type' => 'sales_invoice',
            'reference_id'   => $invoice->id,
            'description'    => "فاتورة بيع - {$invoiceNumber}",
            'created_by'     => $this->createdByUserId,
            'created_at'     => $data['date'],
            'updated_at'     => $data['date'],
        ]);

        if ($paidAmount > 0) {
            FinancialTransaction::create([
                'store_id'       => $this->storeId,
                'party_type'     => PartyType::CUSTOMER,
                'party_id'       => $customer['id'],
                'type'           => TransactionType::CREDIT,
                'amount'         => $paidAmount,
                'reference_type' => 'sales_invoice_payment',
                'reference_id'   => $invoice->id,
                'description'    => "دفعة بيع - {$invoiceNumber}",
                'created_by'     => $this->createdByUserId,
                'created_at'     => $data['date'],
                'updated_at'     => $data['date'],
            ]);
        }
    }
}