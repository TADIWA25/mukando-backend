# TODO: Loan APIs Implementation

## Task
Create APIs and routes for the frontend to access loan information and members with loans, tracking group members and their contributions.

## Plan

### Step 1: Update Loan Model ✅
- Add `groupMember()` relationship
- Add helper methods

### Step 2: Update GroupMember Model ✅
- Add `loans()` relationship

### Step 3: Create Api/LoanController.php ✅
- `index()` - Get all loans for a group with member details
- `show()` - Get loan details for a specific loan
- `store()` - Apply for a loan (create new loan)
- `update()` - Update loan status
- `destroy()` - Delete/cancel a loan

### Step 4: Update routes/api.php ✅
- Add loan routes for CRUD operations

### Step 5: Test the implementation ✅
- Verified routes work correctly

## Status: COMPLETED ✅


