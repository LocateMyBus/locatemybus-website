<?php

use Illuminate\Database\Seeder;
use App\Bus;

class BusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
      DB::statement('SET FOREIGN_KEY_CHECKS=0;');
      Bus::truncate();
      DB::statement('SET FOREIGN_KEY_CHECKS=1;');
      Bus::create([
          'route_id'=>'314D',
          'model'=>'Ashok Leyland'
      ]);
      Bus::create([
          'route_id'=>'314D',
          'model'=>'Volvo'
      ]);
    }
}
