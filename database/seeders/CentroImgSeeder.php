<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CentroImgSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('centro_educativo')
            ->where('id', 1)
            ->update([
                'img' => 'https://palmasdegrancanaria.es/wp-content/uploads/2023/09/AF1QipNFi-KkELy607GX3R-hpbDpsL5p5i44wQ5TkavV.jpeg',
            ]);
    }
}
