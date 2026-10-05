<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct()
    {
       $this->categoryService = new \App\Services\CategoryService();
    }
    public function getCategories(){
        $categories = $this->categoryService->getCategories();
        return $categories;
    }

    public function getProfessions($category_id){
        $professions = $this->categoryService->getProfessions($category_id);
        return $professions;
    }

    public function getformated()
    {
        return $this->categoryService->getFormatedCat();
    }

    public function getMyCategories()
    {
        return $this->categoryService->getMyCategories();
    }

    public function addUserCategory($categoryId)
    {
        return $this->categoryService->addUserCategory($categoryId);
    }
}
