<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Menu;
use App\Models\MenuOption;
use App\Models\Store;
use App\Models\MenuCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MenuOptionTest extends TestCase
{
    use RefreshDatabase;
    
    private Menu $menu;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $store = Store::create([
            'name' => 'テスト店舗',
            'is_active' => true,
        ]);
        
        $category = MenuCategory::create([
            'store_id' => $store->id,
            'name' => 'テストカテゴリー',
            'is_active' => true,
        ]);
        
        $this->menu = Menu::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'テストメニュー',
            'price' => 5000,
            'duration_minutes' => 60,
            'is_available' => true,
        ]);
    }
    
    public function test_オプション作成()
    {
        // Act
        $option = MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => 'アイマスク',
            'description' => 'リラックス効果のあるアイマスク',
            'price' => 500,
            'duration_minutes' => 10,
            'is_active' => true,
            'is_required' => false,
            'max_quantity' => 1,
        ]);
        
        // Assert
        $this->assertDatabaseHas('menu_options', [
            'name' => 'アイマスク',
            'price' => 500,
        ]);
        
        $this->assertEquals($this->menu->id, $option->menu_id);
        $this->assertEquals('アイマスク', $option->name);
        $this->assertEquals(500, $option->price);
        $this->assertEquals(10, $option->duration_minutes);
    }
    
    public function test_フォーマット済み価格表示()
    {
        // Arrange
        $option = MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '有料オプション',
            'price' => 1500,
            'is_active' => true,
        ]);
        
        $freeOption = MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '無料オプション',
            'price' => 0,
            'is_active' => true,
        ]);
        
        // Act & Assert
        $this->assertEquals('¥1,500', $option->formatted_price);
        $this->assertEquals('無料', $freeOption->formatted_price);
    }
    
    public function test_フォーマット済み時間表示()
    {
        // Arrange
        $option = MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '時間追加オプション',
            'price' => 1000,
            'duration_minutes' => 15,
            'is_active' => true,
        ]);
        
        $noTimeOption = MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '時間なしオプション',
            'price' => 500,
            'duration_minutes' => 0,
            'is_active' => true,
        ]);
        
        // Act & Assert
        $this->assertEquals('+15分', $option->formatted_duration);
        $this->assertEquals('', $noTimeOption->formatted_duration);
    }
    
    public function test_アクティブオプションスコープ()
    {
        // Arrange
        MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => 'アクティブオプション',
            'price' => 500,
            'is_active' => true,
        ]);
        
        MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '非アクティブオプション',
            'price' => 500,
            'is_active' => false,
        ]);
        
        // Act
        $activeOptions = MenuOption::active()->get();
        
        // Assert
        $this->assertCount(1, $activeOptions);
        $this->assertEquals('アクティブオプション', $activeOptions->first()->name);
    }
    
    public function test_必須オプションスコープ()
    {
        // Arrange
        MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '必須オプション',
            'price' => 500,
            'is_active' => true,
            'is_required' => true,
        ]);
        
        MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '任意オプション',
            'price' => 500,
            'is_active' => true,
            'is_required' => false,
        ]);
        
        // Act
        $requiredOptions = MenuOption::required()->get();
        $optionalOptions = MenuOption::optional()->get();
        
        // Assert
        $this->assertCount(1, $requiredOptions);
        $this->assertEquals('必須オプション', $requiredOptions->first()->name);
        
        $this->assertCount(1, $optionalOptions);
        $this->assertEquals('任意オプション', $optionalOptions->first()->name);
    }
    
    public function test_並び順でのソート()
    {
        // Arrange
        MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '3番目',
            'price' => 500,
            'sort_order' => 3,
            'is_active' => true,
        ]);
        
        MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '1番目',
            'price' => 500,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        
        MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '2番目',
            'price' => 500,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        
        // Act
        $sortedOptions = MenuOption::ordered()->get();
        
        // Assert
        $this->assertEquals('1番目', $sortedOptions[0]->name);
        $this->assertEquals('2番目', $sortedOptions[1]->name);
        $this->assertEquals('3番目', $sortedOptions[2]->name);
    }
    
    public function test_メニューとのリレーション()
    {
        // Arrange
        $option = MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => 'リレーションテスト',
            'price' => 500,
            'is_active' => true,
        ]);
        
        // Act
        $relatedMenu = $option->menu;
        
        // Assert
        $this->assertInstanceOf(Menu::class, $relatedMenu);
        $this->assertEquals($this->menu->id, $relatedMenu->id);
        $this->assertEquals('テストメニュー', $relatedMenu->name);
    }
    
    public function test_最大数量制限()
    {
        // Arrange
        $option = MenuOption::create([
            'menu_id' => $this->menu->id,
            'name' => '数量制限オプション',
            'price' => 500,
            'is_active' => true,
            'max_quantity' => 3,
        ]);
        
        // Assert
        $this->assertEquals(3, $option->max_quantity);
        $this->assertIsInt($option->max_quantity);
    }
}