<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\CommentRequest;
use App\Http\Requests\ContactRequest;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class InterfaceClientController extends Controller
{

    public function homePage()
    {

        $data['products'] = Product::select('id', 'name', 'image', 'price', 'status')->where('status', 1)->orderBy('name', 'ASC')->get();
        $data['productss'] = Product::select('id', 'name', 'image', 'price', 'status')->where('status', 1)->orderBy('id', 'DESC')->get();

        return view('client.home_page', $data);
    }

    public function showProducts(Request $request)
    {

        $data['categories'] = Category::all();
        $data['category_id'] = $request->get('category_id');

        $data['products'] = Product::select('id', 'name', 'image', 'price', 'category_id')
            ->where('category_id', 'like', '%' . $data['category_id'] . '%')
            ->where('status', 1)->paginate(12);

        return view('client.product-page', $data);
    }

    public function productDetail($id)
    {
        $data['product'] = Product::find($id);
        $data['productCate'] = Product::where('category_id', $data['product']->category_id)
            ->where('status', 1)
            ->where('id', '<>', $id)
            ->get();
        $data['comments'] = Comment::with('user')->where('product_id', $id)->paginate(6);

        return view('client.product-detail', $data);
    }

    public function contactUs()
    {
        return view('client.contact-us');
    }

    public function carts()
    {

        $data['carts'] = Session::get('carts');

        if (!$data['carts']) {
            return view('client.empty-cart');
        }

        $total = 0;
        foreach ($data['carts'] as $cart) {

            $total += $cart['price'] * $cart['quant'];
        }
        $data['total'] = number_format($total, 0, ',', '.');

        return view('client.cart', $data);
    }

    public function addToCart(Product $product, Request $request)
    {
        if (isset($request->quant)) {
            $product->quant = $request->quant;
        } else {
            $product->quant = 1;
        }

        if (Session::get('carts') == null) {
            $carts = [];
            $carts[$product->id] = $product;
            Session::put('carts', $carts);

            return redirect()->back()->with('success', 'Th??m s???n ph???m v??o gi??? h??ng th??nh c??ng');
        } else {
            $carts = Session::get('carts');
            if (array_key_exists($product->id, $carts)) {
                $carts[$product->id]->quant += $product->quant;
            } else {
                $carts[$product->id] = $product;
            }
            Session::put('carts', $carts);
        }
        return redirect()->back()->with('success', 'Th??m s???n ph???m v??o gi??? h??ng th??nh c??ng');
    }

    public function removeItemCart($id)
    {

        $carts = Session::get('carts');
        unset($carts[$id]);
        Session::put('carts', $carts);
        return redirect()->back()->with('success', 'X??a s???n ph???m kh???i gi??? h??ng th??nh c??ng!');
    }

    public function order()
    {
        if (!Auth::check()) {
            return redirect()->back()->with('error', 'B???n c???n ????ng nh???p ????? ?????t h??ng');
        }

        $data['orders'] = Order::select('id', 'code', 'name', 'user_id', 'product_name', 'status', 'price', 'quantity', 'total', 'address', 'email', 'created_at')
            ->where('user_id', Auth::user()->id)
            ->paginate(4);

        return view('client.order', $data);
    }

    public function orderDetroy(Order $order)
    {

        if ($order->status == 0) {
            $order->status = 1;
        }

        $order->save();

        return redirect()->route('order')->with('success', 'H???y ????n h??ng th??nh c??ng!');
    }

    public function contactStore(ContactRequest $request)
    {
        if (Auth::check() == false) {
            return redirect()->back()->with('error', 'B???n ph???i ????ng nh???p ????? ???????c g???i th??ng tin!');
        }

        $contact = new Contact($request->all());

        $contact->save();

        return redirect()->back()->with('success', 'G???i contact th??nh c??ng!');
    }

    public function commentStore(CommentRequest $request, $id)
    {

        if (Auth::check() == false) {
            return redirect()->back()->with('error', 'B???n c???n ????ng nh???p ????? b??nh lu???n!');
        }

        $comment = new Comment($request->all());
        $comment->product_id = $id;
        $comment->user_id = Auth::user()->id;
        $comment->name = Auth::user()->name;
        $comment->email = Auth::user()->email;

        $comment->save();

        return redirect()->back()->with('success', 'G???i b??nh lu???n th??nh c??ng!');
    }

    public function commentDestroy($id)
    {

        $comment = Comment::find($id);
        $comment->delete();
        return redirect()->back()->with('success', 'X??a comment th??nh c??ng!');
    }

    //checkout

    public function checkout()
    {
        if (Auth::check()) {
            $data['user'] = User::find(Auth::user()->id);
        } else {
            $data['user'] = null;
        }

        $data['carts'] = Session::get('carts');
        if (!$data['carts']) {
            return view('client.empty-cart');
        }
        $total = 0;
        foreach ($data['carts'] as $cart) {
            $total += $cart['price'] * $cart['quant'];
        }
        $data['total'] = number_format($total, 0, ',', '.');
        return view('client.checkout', $data);
    }

    public function checkoutAction(CheckoutRequest $request)
    {

        if (Auth::check() == false) {
            return back()->with('error', 'B???n c???n ????ng nh???p ????? thanh to??n!');
        }

        $order = new Order($request->all());

        $order->code = 'DH' . rand(1, 1000);
        $order->user_id = Auth::user()->id;

        foreach (Session::get('carts') as $cart) {
            $order->product_name .= $cart['name'] . ',';
            $order->price .= $cart['price'] . ',';
            $order->quantity .= $cart['quant'] . ',';
        }

        $total = 0;
        foreach (Session::get('carts') as $cart) {

            $total += $cart['price'] * $cart['quant'];
        }
        $order->total = number_format($total, 0, ',', '.');

        $order->save();
        Session::forget('carts');

        return redirect()->route('order')->with('success', '?????t h??ng th??nh c??ng');
    }
}
