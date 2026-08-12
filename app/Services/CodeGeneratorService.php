<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CodeGeneratorService
{
    public function generate(string $prefix, int $sequence): string
    {
        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function next(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            DB::table('document_sequences')->insertOrIgnore(['key'=>$prefix,'next_value'=>1,'created_at'=>now(),'updated_at'=>now()]);
            $row = DB::table('document_sequences')->where('key',$prefix)->lockForUpdate()->first();
            DB::table('document_sequences')->where('id',$row->id)->update(['next_value'=>$row->next_value + 1,'updated_at'=>now()]);
            return $this->generate($prefix, (int) $row->next_value);
        });
    }
}
