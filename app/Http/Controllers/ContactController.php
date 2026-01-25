<?php

// ============================================================================
// FILE: app/Http/Controllers/ContactController.php
// NEW - Based on routes references
// ============================================================================

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function getContacts()
    {
        $contactInfo = ContactSubmission::orderBy('created_at', 'desc')
            ->get();

        if ($contactInfo->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No contact information found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'contact_info' => $contactInfo,
        ]);
    }

    public function contactSubmit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:500',
            'message' => 'required|string',
            'attachment' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $submission = ContactSubmission::create([
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => 'New',
            'attachment' => $request->attachment,
        ]);

        return response()->json([
            'status' => true,
            'submission' => $submission,
            'message' => 'Your message has been submitted successfully',
        ], 201);
    }

    public function deleteContactForm($id)
    {
        $submission = ContactSubmission::findOrFail($id);
        $submission->delete();

        return response()->json([
            'status' => true,
            'message' => 'Contact submission deleted successfully',
        ]);
    }

    public function getKeywords()
    {
        $keywords = Keyword::withCount(['articles'])
            ->where('status', 'Active')
            ->has('articles')
            ->orderBy('keyword', 'asc')
            ->get();

        $keywordsWithArticles = Keyword::with(['articles' => function ($q) {
            $q->select('articles.id', 'articles.mhid');
        }])
            ->has('articles')
            ->get();

        $articlesMap = [];
        foreach ($keywordsWithArticles as $keyword) {
            $articlesMap[$keyword->id] = $keyword->articles->pluck('id')->toArray();
        }

        return response()->json([
            'status' => true,
            'keywords' => $keywords,
            'articlesMap' => $articlesMap,
        ]);
    }

    public function addUpdateKeyword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required|string|max:255',
            'id' => 'nullable|exists:keywords,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('id') && $request->id) {
            // Update
            $keyword = Keyword::findOrFail($request->id);
            $keyword->keyword = $request->keyword;
            $keyword->save();
            $message = 'Keyword updated successfully';
        } else {
            // Create
            $keyword = Keyword::create([
                'keyword' => $request->keyword,
                'status' => 'Active',
            ]);
            $message = 'Keyword added successfully';
        }

        return response()->json([
            'status' => true,
            'keyword' => $keyword,
            'message' => $message,
        ]);
    }

    public function deleteKeyword($id)
    {
        $keyword = Keyword::findOrFail($id);

        // Check if used in articles
        if ($keyword->articles()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete keyword that is used in articles',
            ], 400);
        }

        $keyword->delete();

        return response()->json([
            'status' => true,
            'message' => 'Keyword deleted successfully',
        ]);
    }

    public function sendMail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipient_email' => 'required|email',
            'recipient_name' => 'required|string',
            'recipient_type' => 'required|string',
            'subject' => 'required|string',
            'message' => 'required|string',
            'email_type' => 'required|string',
            'recipient_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            \Illuminate\Support\Facades\Mail::to($request->recipient_email)->send(
                new \App\Mail\GeneralMail(
                    $request->recipient_name,
                    $request->subject,
                    $request->message,
                    $request->recipient_type,
                    $request->email_type
                )
            );

            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
            ], 500);
        }
    }
}
