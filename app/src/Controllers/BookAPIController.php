<?php

namespace App\Controllers;

use App\Models\Book;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Controller;

class BookAPIController extends Controller
{
    private static $allowed_actions = [
        'index', 'view', 'createBook', 'update', 'delete'
    ];

    private static $url_handlers = [
        'GET books'         => 'index',
        'GET books/$ID'     => 'view',
        'POST books'        => 'createBook',
        'PUT books/$ID'     => 'update',
        'DELETE books/$ID'  => 'delete'
    ];

    public function index(HTTPRequest $request)
    {
        $books = Book::get();
        $result = [];

        foreach ($books as $book) {
            $result[] = $book->toMap();
        }

        return json_encode($result);
    }

    public function view(HTTPRequest $request)
    {
        $id = $request->param('ID');
        $book = Book::get()->byID($id);

        if (!$book) {
            return (new HTTPResponse('Not Found', 404));
        }

        return json_encode($book->toMap());
    }

    public function createBook(HTTPRequest $request)
    {
        $data = json_decode($request->getBody(), true);

        $book = Book::create();
        $book->update($data);
        $book->write();

        return json_encode(['message' => 'Book created', 'id' => $book->ID]);
    }

    public function update(HTTPRequest $request)
    {
        $id = $request->param('ID');
        $book = Book::get()->byID($id);

        if (!$book) {
            return (new HTTPResponse('Not Found', 404));
        }

        $data = json_decode($request->getBody(), true);
        $book->update($data);
        $book->write();

        return json_encode(['message' => 'Book updated']);
    }

    public function delete(HTTPRequest $request)
    {
        $id = $request->param('ID');
        $book = Book::get()->byID($id);

        if (!$book) {
            return (new HTTPResponse('Not Found', 404));
        }

        $book->delete();

        return json_encode(['message' => 'Book deleted']);
    }
}
