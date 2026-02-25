use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

public function register(Request $request)
{
    $request->validate([
        'name' => 'required',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
        'phone' => 'required'
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'phone' => $request->phone,
        'password' => Hash::make($request->password),
    ]);

    return response()->json([
        'message' => 'User registered successfully',
        'user' => $user
    ]);
}