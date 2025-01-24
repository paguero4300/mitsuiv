use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToVehicleDocuments extends Migration
{
    public function up()
    {
        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->index(['vehicle_id', 'type']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down()
    {
        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'type']);
            $table->dropIndex(['type', 'created_at']);
        });
    }
} 