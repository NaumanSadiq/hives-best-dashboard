<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\Operations;

require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.mysql.database'=>'hives_best_qa']); DB::purge('mysql');
$key='long-invoice-'.Str::lower(Str::random(8));
$product=DB::table('products')->insertGetId(['name'=>'Wholesome roasted almond snack pack · a carefully prepared and naturally delicious selection','stock_key'=>$key,'kind'=>'snack','pack_quantity'=>1,'price'=>10000,'low_stock'=>2]);
DB::table('batches')->insert(['reference'=>$key,'stock_key'=>$key,'received_on'=>today()->toDateString(),'original_quantity'=>100,'physical'=>100,'reserved'=>0,'purchase'=>500000,'packaging'=>100000,'branding'=>50000,'other'=>0]);
$id=app(Operations::class)->saveOrder(['customer'=>'Long Invoice QA','phone'=>'03000000000','address'=>str_repeat('Disposable delivery details. ',15),'ordered_on'=>today()->toDateString(),'discount'=>'0','delivery'=>'200','courier'=>'150','tracking'=>'','notes'=>'Never show these private notes'],array_fill(0,50,['product_id'=>$product,'quantity'=>1,'price'=>'100']),(string)Str::uuid());
echo json_encode(['invoice'=>app(Operations::class)->issueInvoice($id)]);
