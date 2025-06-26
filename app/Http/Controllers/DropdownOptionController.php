<?php

namespace App\Http\Controllers;

use App\Models\DropdownOptions;
use Illuminate\Http\Request;

class DropdownOptionController extends Controller
{
    // Get dropdown option by ID
    public function getOptionById($id)
    {
        $option = DropdownOptions::find($id);

        if (!$option) {
            return response()->json(['message' => 'Option not found'], 404);
        }

        return response()->json($option);
    }

    // Get all dropdown options by parent_id
    public function getOptionsByParentId($parent_id)
    {
        $options = DropdownOptions::where('parent_id', $parent_id)
        ->where('status_id', 1)            
        ->get();

        if ($options->isEmpty()) {
            return response()->json(['message' => 'No options found for this parent_id'], 404);
        }

        return response()->json($options);
    }
}

