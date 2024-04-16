<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use App\Models\Follow;

// use Illuminate\Support\Facades\Mail;
// use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Storage;


class UserController extends Controller
{

    public function register(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'username' => 'required|unique:users|max:255',
                'email' => 'required|email|max:255|unique:users',
                'password' => 'required|min:8|max:30|confirmed|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&]/',
            ]);

            if ($validator->fails()) {
                // Return validation errors as JSON response
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Start a database transaction
            DB::beginTransaction();

            // Save user data
            $user = new User();
            $user->username = $request->input('username');
            $user->email = $request->input('email');
            $user->password = bcrypt($request->input('password')); // Hash the password
            // Set other fields (if any)

            $user->save();

            // Commit the transaction
            DB::commit();

            // Return a success response
            return response()->json(['message' => 'User registered successfully',        'redirect' => '/signin'
        ]);
        } catch (\Exception $e) {
            // Handle other exceptions and roll back the transaction
            DB::rollBack();

            // Log the error or return an appropriate error response
            return response()->json(['error' => 'User registration failed'], 500);
        }
    }




    


public function login(Request $request)
{
   

    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|',
    ]);

    if ($validator->fails()) {
        // Return validation errors as JSON response
        return response()->json(['errors' => $validator->errors()], 422);
    }
    // Attempt to authenticate the user
    if (!Auth::attempt($request->only('email', 'password'))) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    // Get the currently authenticated user
    $user = Auth::user();


    // Update the isActive column to 1
    $user->update(['isActive' => 1]);
    // Create a token for the user
    $token = $user->createToken('auth_token')->accessToken;


    // Return a successful response with the token
    return response([
        'message' => 'Login successful',
        'redirect' => '/home',
        'token'=>$token,

    ]);
}



public function userDetail(Request $request)
    {
        // Retrieve the authenticated user based on the token
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Construct the profile picture URL
        $profilePictureUrl = asset('storage/profile/' . $user->profilePicture);

        // Prepare the user details
        $userDetails = [
            'username' => $user->username,
            'email' => $user->email,
            'profilePictureURL' => $profilePictureUrl,
            'userBio' => $user->userBio,
            'isActive' => $user->isActive
        ];

        
        // Return the user details as a JSON response
        return response()->json($userDetails);
    }



    
function updateDetails(Request $request){
    $user = Auth::user();

    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    // Validate input data
    $validator = Validator::make($request->all(), [
        'bio' => 'nullable|string|max:100',
        'media' => 'nullable|file|mimes:jpg,jpeg,png|max:20480',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    DB::beginTransaction(); // Start a new database transaction

    try {
        // Update userBio if provided
        if ($request->has('bio')) {
            $user->userBio = $request->bio;
        }

         // Update profilePicture if provided
         // Update profilePicture if provided
         if ($request->hasFile('media')) {
            $filename = $request->file('media')->getClientOriginalName();
            $uniqueFilename = 'profile_' . time() . '.' . $filename;
            $filePath = $request->file('media')->storeAs('profile', $uniqueFilename, 'public');
            $user->profilePicture = $uniqueFilename;
        }

        $user->save(); // Save the changes

        DB::commit(); // Commit the transaction

        // Log success


        return response()->json(['message' => 'Details updated successfully']);
    } catch (\Exception $e) {
        DB::rollBack(); // Rollback the transaction on error

        // Log error

        return response()->json(['error' => 'Update failed', 'message' => $e->getMessage()], 500);
    }
}



    public function logout(Request $request)
    {


        $user = Auth::user();
        $user->update(['isActive' => 0]);

        // Get the token that the user is currently authenticated with
        $token = $request->user()->token();
    
        // Revoke the token to log the user out
        $token->revoke();
    
        // Forget the cookie
        
    
        // Update the isActive column to 1
        // Return a response indicating the user has been logged out
        return response([
            'message' => 'Logout successful',
        ]);
    }
    



    public function search(Request $request)
{
    // Validate the incoming search text.
    $validatedData = $request->validate([
        'searchText' => 'required|string|min:3',
    ]);

    // Get the current authenticated user's ID.
    $currentUserId = auth()->id();

    // Check if the current user is following each user in the search results.
   

    // Perform the search query on the users table.
    $users = User::where('username', 'LIKE', '%' . $validatedData['searchText'] . '%')
                  ->get(['id', 'username','profilePicture']);
    
    $users->each(function ($user) use ($currentUserId) {
        $user->isFollowing = Follow::where('user_id', $currentUserId)
            ->where('target_id', $user->id)
            ->exists();
                });              

    // Append the profile picture URL to each user.
    $users->each(function ($user) {
        $user->profilePictureUrl = asset('storage/profile/' . $user->profilePicture);
    });

    // Return the JSON response.
    return response()->json(["search"=>$users]);
}


    
}
