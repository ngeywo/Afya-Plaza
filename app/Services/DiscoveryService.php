<?php
namespace App\Services;
use App\Models\ClinicSession;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Support\Collection;
/**
 * Phase 15: Marketplace Search & Discovery Engine.
 */
class DiscoveryService
{

    public function search(array $params): array
    {
        $date=!empty($params['date'])?Carbon::parse($params['date']):today();
        $perPage=min((int)($params['per_page']??12),48);
        $page=max((int)($params['page']??1),1);
        $query=ClinicSession::query()
            ->where('session_date',$date->toDateString())
            ->where('status','confirmed')
            ->where('doctor_confirmation','confirmed')
            ->where('facility_confirmation','confirmed')
            ->whereHas('doctor',fn($q)=>$q->where('is_active',true))
            ->whereHas('facility',fn($q)=>$q->where('is_active',true));
        $this->applyFilters($query,$params);
        $sessions=$query->with(['doctor.specialties','facility.county','facilityLocation'])->orderBy('start_time')->get();
        $doctorGroups=$sessions->groupBy('doctor_id');
        if($doctorGroups->isEmpty()){return $this->emptyResult($date,$perPage,$page,$params);}
        $doctorPrimarySessionIds=$doctorGroups->map(fn($g)=>$g->sortBy('start_time')->first()->id)->values()->toArray();
        $total=count($doctorPrimarySessionIds);
        $lastPage=max(1,(int)ceil($total/$perPage));
        $offset=($page-1)*$perPage;
        $pageSessionIds=array_slice($doctorPrimarySessionIds,$offset,$perPage);
        $sessionsById=$sessions->keyBy('id');
        $data=collect($pageSessionIds)->map(function($sid)use($sessionsById,$sessions,$date){
            $primary=$sessionsById->get($sid);
            if(!$primary)return null;
            $did=$primary->doctor_id;
            $docSess=$sessions->filter(fn($s)=>$s->doctor_id===$did);
            return $this->buildDoctorSearchResult($primary,$docSess,$date);
        })->filter()->values();
        return['data'=>$data,'meta'=>['total'=>$total,'per_page'=>$perPage,'current_page'=>$page,'last_page'=>$lastPage,'date'=>$date->toDateString(),'day'=>$date->format('l, F j, Y')],'suggestions'=>$this->buildSuggestions($date,$params)];
    }

    public function searchNextAvailable(array $params,Carbon $fromDate,int $daysAhead=14):array
    {
        for($i=1;$i<=$daysAhead;$i++){
            $date=$fromDate->copy()->addDays($i);
            $params['date']=$date->toDateString();$params['per_page']=6;
            $result=$this->search($params);
            if(!empty($result['data']))
            {
                $result['meta']['suggestion_label']=$date->format('l, F j');
                $result['meta']['suggestion_date']=$date->toDateString();return $result;
            }
        }return['data'=>[],'meta'=>[],'suggestions'=>[]];
    }

    private function buildDoctorSearchResult(ClinicSession $primary,Collection $doctorSessions,Carbon $date):array
    {
        $doctor=$primary->doctor;
        $primarySpec=$doctor->specialties->first(fn($s)=>$s->pivot?->is_primary)??$doctor->specialties->first();
        $allSessions=$doctorSessions->map(fn($s)=>[
            'id'=>$s->id,
            'session_date'=>$s->session_date->format('Y-m-d'),
            'start_time'=>substr($s->start_time,0,5),
            'end_time'=>substr($s->end_time,0,5),
            'facility'=>['id'=>$s->facility->id,'name'=>$s->facility->name,'city'=>$s->facility->city,'county'=>$s->facility->county?->name,'address'=>$s->facility->address,'is_verified'=>$s->facility->is_verified],
            'facility_location'=>$s->facilityLocation?['id'=>$s->facilityLocation->id,'name'=>$s->facilityLocation->name,'address'=>$s->facilityLocation->address]:null,
            'available_slots'=>$s->available_slots,'max_appointments'=>$s->max_appointments,'consultation_fee'=>$s->consultation_fee,'is_confirmed'=>$s->is_confirmed,'is_bookable'=>$s->is_bookable,'status'=>$s->status,
        ])->sortBy('start_time')->values();
        return[
            'doctor'=>[
                'id'=>$doctor->id,'slug'=>$doctor->slug,'name'=>$doctor->display_name,'avatar'=>$doctor->avatar,
                'is_verified'=>$doctor->is_verified,'is_featured'=>$doctor->is_featured,'consultation_fee'=>$doctor->consultation_fee,'years_of_experience'=>$doctor->years_of_experience,
                'primary_specialty'=>$primarySpec?['id'=>$primarySpec->id,'name'=>$primarySpec->name,'icon'=>$primarySpec->icon]:null,
                'specialties'=>$doctor->specialties->map(fn($s)=>['id'=>$s->id,'name'=>$s->name,'icon'=>$s->icon,'is_primary'=>(bool)$s->pivot->is_primary]),
            ],
            'primary_session'=>$allSessions->first(),
            'all_sessions'=>$allSessions,
            'session_count'=>$allSessions->count(),
        ];
    }

    private function applyFilters($query,array $params):void
    {
        if(!empty($params['q']))$query->whereHas('doctor',fn($q)=>$q->where('display_name','like','%' .$params['q'].'%'));
        if(!empty($params['specialty_id']))$query->whereHas('doctor.specialties',fn($q)=>$q->where('specialties.id',$params['specialty_id']));
        if(!empty($params['county_id']))$query->whereHas('facility',fn($q)=>$q->where('county_id',$params['county_id']));
        if(!empty($params['city']))$query->whereHas('facility',fn($q)=>$q->where('city','like','%' .$params['city'].'%'));
        if(!empty($params['facility_id']))$query->where('facility_id',$params['facility_id']);
        if(!empty($params['verified_only']))$query->whereHas('doctor',fn($q)=>$q->where('is_verified',true));
    }

    private function buildSuggestions(Carbon $date,array $params):array
    {
        $suggestions=[];
        $tomorrow=$date->copy()->addDay();
        $suggestions[]=['type'=>'date','label'=>'Try Tomorrow','date'=>$tomorrow->toDateString(),'day'=>$tomorrow->format('l, M j'),'message'=>"No doctors found for {$date->format('l, M j')}. Check {$tomorrow->format('l')} instead."];
        if(!empty($params['specialty_id']))$suggestions[]=['type'=>'clear_filter','label'=>'Browse All Specialties','message'=>'Try browsing all specialties in this area.'];
        $nearbyDates=$this->findNearbyDatesWithSessions($date,3,$params);
        foreach($nearbyDates as $altDate)$suggestions[]=['type'=>'date','label'=>$altDate->format('l'),'date'=>$altDate->toDateString(),'day'=>$altDate->format('l, M j'),'message'=>"Doctors available on {$altDate->format('l, M j')}."];
        return $suggestions;
    }

    private function findNearbyDatesWithSessions(Carbon $fromDate,int $limit,array $params):array
    {
        $found=[];
        for($i=1;$i<=30&&count($found)<$limit;$i++){
            $d=$fromDate->copy()->addDays($i);
            $count=ClinicSession::query()
                ->where('session_date',$d->toDateString())
                ->where('status','confirmed')
                ->where('doctor_confirmation','confirmed')
                ->where('facility_confirmation','confirmed')
                ->whereHas('doctor',fn($q)=>$q->where('is_active',true))
                ->whereHas('facility',fn($q)=>$q->where('is_active',true))
                ->when(!empty($params['specialty_id']),fn($q)=>$q->whereHas('doctor.specialties',fn($sq)=>$sq->where('specialties.id',$params['specialty_id'])))
                ->when(!empty($params['city']),fn($q)=>$q->whereHas('facility',fn($fq)=>$fq->where('city','like','%' .$params['city'].'%')))
                ->count();
            if($count>0)$found[]=$d;
        }return $found;
    }

    private function emptyResult(Carbon $date,int $perPage,int $page,array $params):array
    {
        return['data'=>[],'meta'=>['total'=>0,'per_page'=>$perPage,'current_page'=>$page,'last_page'=>0,'date'=>$date->toDateString(),'day'=>$date->format('l, F j, Y')],'suggestions'=>$this->buildSuggestions($date,$params)];
    }
}
