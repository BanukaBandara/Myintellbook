<?php

namespace App\Services;
use App\Models\Category;
use App\Models\profession;
use Illuminate\Support\Facades\Log; 


class CategoryService{

    public function getCategories(){
        try{
            $categories = Category::get(['id','name']);
            return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $categories->toArray(),
            ], 200);
        }catch(\Exception $e){ 
            log::error('CategoryService @getCategories: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'category not found',
            ], 500);
        }
        
    }

    public function getProfessions($category_id){
        try{
            $professions = profession::where('category_id', $category_id)->get();
            return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $professions->toArray(),
            ], 200);
        }catch(\Exception $e){ 
            log::error('CategoryService @getProfessions: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'category not found',
            ], 500);
        }
        
    }

    public function getFormatedCat()
    {
        try{
            $categories= \App\Models\Category::with('professions')->get();


            $formated = $categories->map(function($category, $index){
                
            return [
                    "key"=>$category->id,
                    "label"=>$category->name,
                    "children"=>$category->professions->map(function($profession,$index){
                        return[
                            "key"=>$profession->id,
                            "label"=>$profession->name,
                            "icon"=>"pi pi-caret-right"
                        ];
                    })
                ];
                
            });
            return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $formated,
            ], 200);
        }catch(\Exception $e){ 
            log::error('CategoryService @getFormatedCat: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'category not found',
            ], 500);
        }
    }

    public function getMyCategories()
    {
        try{

            $categories = \App\Models\Category::whereHas('professions.users', function ($q) {
                                $q->where('user_id', auth()->id());
                            })
                            ->with(['professions' => function ($query) {
                                $query->whereHas('users', function ($q) {
                                    $q->where('user_id', auth()->id());
                                });
                            }])
                            ->get();


            $formated = $categories->map(function($category, $index){
                
            return [
                    "key"=>$category->id,
                    "label"=>$category->name,
                    "children"=>$category->professions->map(function($profession,$index){
                        return[
                            "key"=>$profession->id,
                            "label"=>$profession->name,
                            "icon"=>"pi pi-caret-right"
                        ];
                    })
                ];
                
            });
            return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $formated,
            ], 200);
        }catch(\Exception $e){ 
            log::error('CategoryService @getFormatedCat: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'category not found',
            ], 500);
        }
    }

    public function addUserCategory($categoryId)
    {
        try{
            $profession = Profession::find($categoryId);

            if(!$profession){
                return response()->json([
                    'code' => 404,
                    'status' => false,
                    'message' => 'Category not found',
                ], 404);
            }

            if($profession->users()->where('user_id', auth()->user()->id)->exists())
            {
                $profession->users()->detach(auth()->user()->id);

            }else{
                $profession->users()->attach(auth()->user()->id);
            }
            

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Professions added to user successfully',
            ], 200);
        }catch(\Exception $e){ 
            log::error('CategoryService @addUserCategory: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'An error occurred while adding professions to user',
            ], 500);
        }
    }
}