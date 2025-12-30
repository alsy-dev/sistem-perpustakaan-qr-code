<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Query;
use CodeIgniter\I18n\Time;

class API extends BaseController
{

    use ResponseTrait;

    protected BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
        // $this->rack_model = new RackModel;
    }

    public function recommend($id)
    {
        $sql = "
            WITH model AS (
                SELECT l1.book_id as book_id1, l2.book_id as book_id2, COUNT(*) as count
            	FROM loans l1, loans l2
            	WHERE l1.member_id = l2.member_id AND l1.book_id != l2.book_id AND l1.book_id IN (SELECT book_id FROM loans WHERE member_id = $id)
                GROUP BY l1.book_id, l2.book_id
              	ORDER BY count DESC
                LIMIT 3
            )
            SELECT *
            FROM books
            WHERE id IN (
                SELECT book_id2
                FROM model
            );
        ";

        $query = $this->db->query($sql, [$id]);

        $results = $query->getResultArray();

        return $this->respond($results, 200);
    }
    
    public function manggil_japrul() {
        $homepage = file_get_contents('http://cisitufc.fun/api/categories/');
        
        return $homepage;
    }
    
    public function member($id = null)
    {
        // 1. Check if ID is provided
        if ($id === null) {
            return $this->fail('Member ID is required', 400);
        }
    
        // 2. Query the specific member by ID
        $builder = $this->db->table('members');
        $builder->select('id, uid, first_name, last_name, email, phone, address, gender');
        $builder->where('id', $id);
        $builder->where('deleted_at', null); // Only fetch active members
        
        $member = $builder->get()->getRowArray();
    
        // 3. Check if member exists
        if (!$member) {
            return $this->failNotFound('Member not found with ID: ' . $id);
        }
    
        // 4. Format the name for the response
        $member['nama_lengkap'] = $member['first_name'] . ' ' . $member['last_name'];
        
        return $this->respond($member, 200);
    }
    
    public function peminjaman($uuid = null)
    {
        if ($uuid === null) {
            return $this->fail('UUID is required', 400);
        }
    
        $builder = $this->db->table('loans');
        // Removed loans.status because it doesn't exist in your DB
        $builder->select('
            loans.id as loan_id, 
            books.title as judul_buku, 
            loans.loan_date, 
            loans.due_date, 
            loans.return_date,
            loans.quantity
        ');
        $builder->join('members', 'members.id = loans.member_id');
        $builder->join('books', 'books.id = loans.book_id');
        $builder->where('members.uid', $uuid);
        
        $results = $builder->get()->getResultArray();
    
        if (empty($results)) {
            return $this->respond([], 200, 'No loan records found');
        }
    
        // Optional: Add a virtual "status" field based on return_date
        foreach ($results as &$row) {
            $row['status'] = ($row['return_date'] === null) ? 'Masih Dipinjam' : 'Sudah Dikembalikan';
        }
    
        return $this->respond($results, 200);
    }

    public function peminjaman_list()
    {
        $builder = $this->db->table('loans');
        $builder->select('
            loans.uid as loan_uid,
            members.first_name, 
            members.last_name, 
            members.uid as member_uid,
            books.title, 
            books.author, 
            books.year,
            loans.quantity, 
            loans.loan_date, 
            loans.due_date, 
            loans.return_date
        ');
        $builder->join('members', 'members.id = loans.member_id');
        $builder->join('books', 'books.id = loans.book_id');
        $builder->where('loans.return_date', null); // Usually APIs list active loans
        
        $results = $builder->get()->getResultArray();
        $now = Time::now(locale: 'id');
    
        foreach ($results as &$row) {
            // Format names and strings
            $row['nama_peminjam'] = $row['first_name'] . ' ' . $row['last_name'];
            $row['info_buku'] = $row['title'] . ' (' . $row['year'] . ')';
            
            // Calculate Status logic exactly like your view
            $dueDate = Time::parse($row['due_date'], locale: 'id');
            
            if ($now->isBefore($dueDate)) {
                $row['status'] = 'Normal';
            } elseif ($now->today()->equals($dueDate)) {
                $row['status'] = 'Jatuh tempo';
            } else {
                $row['status'] = 'Terlambat';
            }
    
            // Clean up unnecessary raw fields
            unset($row['first_name'], $row['last_name']);
        }
    
        return $this->respond($results, 200);
    }
    
    public function rak() {
        // $rak = $this->respond()
    }
}
