<?php
namespace App\Controller;



use App\Controller\AppController;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use CakePdf\Pdf\CakePdf;
use Cake\Cache\Cache;

class QuotesController extends AppController {

	public function initialize() {
      parent::initialize();
      $this->loadComponent('RequestHandler');
      $this->_validViewOptions[] = 'pdfConfig';
  	}

	public function index($id = null) {
		$this->loadModel('Projects');
		$this->loadModel('Regions');

		$this->loadModel('Emails');

		$uf = Cache::read('uf');
		$proyectos = $this->Projects->find('all');
		$regiones = $this->Regions->find('all');
		// if ($id > 0) {
		// 	$cotizacion = $this->Quotations->find('all')->where(['Quotations.id' => $id])->contain(['Clients', 'Projects']);
		// }

		$email = '';
		if($id > 0) {
			$email = $this->Emails->get($id, [
				'contain' => ['Clients']
			]);
		}

		$this->set(compact('proyectos', 'regiones', 'email', 'id', 'cotizacion', 'uf'));
	}

	public function guardar($id = null){

		$this->viewBuilder()->layout('false');
		$this->autoRender = false;
		//pr($id);die;
		$this->loadModel('Quotations');
		$this->loadModel('Clients');
		$this->loadModel('Emails');

		if ($id > 0) {
			$cotizacion = $this->Quotations->get($id);
		}else {
			$cotizacion = $this->Quotations->newEntity();
		}
		if ($this->request->is('ajax')) {
			$params = $this->request->data;

			$registro = $params['data_cliente'];
			$array = explode("&", $registro);
			$rut = explode("=", $array[2]);

			$cliente = $this->Clients->find('all')
				->where(['rut' => $rut[1]])
				->toArray();
			$emails = $this->Emails->find('all')
				->where(['id_client' => $cliente[0]['id']])->toArray();



			$discountAmmount = !empty($params['data_dpto'][8]) ? str_replace(',', '', $params['data_dpto'][8]) : 0;
			$datos = [
				'client_id' => $cliente[0]['id'],
				'project_id' => !empty($params['data_seleccion']) ? $params['data_seleccion'][2] : 0,
				'user_id' => !empty($emails[0]['id_users']) ? $emails[0]['id_users'] : $this->request->session()->read('Auth.User.id'),
				'email_id' => !empty($emails) ? $emails[0]['id'] : 0,
				'user_discount_id' => 0,
				'discount_ammount' => !empty($params['data_dpto']) ? $params['data_dpto'][0] : 0,
				'price_discount' => $discountAmmount,
				'apartment_id' => !empty($params['data_seleccion']) ? $params['data_seleccion'][0] : 0,
				'n_parks' => !empty($params['data_seleccion']) ? $params['data_seleccion'][4] : 0,
				'n_storages' => !empty($params['data_seleccion']) ? $params['data_seleccion'][5] : 0,
				'pie_dues' => !empty($params['data_dpto']) ? $params['data_dpto'][5] : 0,
				'pie_percentage' => !empty($params['data_dpto']) ? $params['data_dpto'][3] : 0,
				'age_range' => !empty($params['data_no_obligatoria']) ? $params['data_no_obligatoria'][0] : null,
				'quotation_type' => !empty($params['data_no_obligatoria']) ? $params['data_no_obligatoria'][1] : null,
				'project_referer' => !empty($params['data_no_obligatoria']) ? $params['data_no_obligatoria'][2] : null,
				'user_type' => !empty($params['data_no_obligatoria']) ? $params['data_no_obligatoria'][3] : null,
				'pie_percentage' => !empty($params['data_dpto']) ? $params['data_dpto'][3] : 0,
				'generated_reservation' => 1,
				'deleted' => 1,
				'finished' => 1
			];

			if ($datos['email_id'] > 0) {
				$emailCotizado =  $this->Emails->get($datos['email_id']);
				if(!empty($emailCotizado)){
					$dataEmail['estado'] = 1;
					$emailCotizado = $this->Emails->patchEntity($emailCotizado, $dataEmail);
					if ($this->Emails->save($emailCotizado)) {
						$body =  'success';
					}
				}
			}

			if (!empty($datos)) {
				$cotizacion = $this->Quotations->patchEntity($cotizacion, $datos);
				if($this->Quotations->save($cotizacion)) {
					$data = [
						'params' => $params,
						'cotizacion' => $cotizacion
					];
					$idReserva = $this->createReservation($cotizacion->id);
					//pr($cotizacion->id);
					$cotizacion->set('reservation_id', $idReserva);
     			$this->response->type('json');
        	$this->response->body($cotizacion);
        	return $this->response;
        } else {
          $this->response->type('json');
          $this->response->body('error');
          return $this->response;
        }
			}
		}
	}

	function update($id = null) {

		$this->autoRender = false;
		$this->viewBuilder()->layout('false');
		$this->loadModel('Quotations');
		$this->loadModel('ReservationUnits');
		if ($this->request->is('post')) {
			$datos = $this->request->data;
			//pr($datos);die;
			if(!empty($datos['units'])){
				$this->ReservationUnits->deleteAll(['quotation_id' => $id]);
				foreach ($datos['units'] as $value) {
					$unit['quotation_id'] = $id;
					$unit['unit_id'] = $value['id'];
					$unit['reservation_ammount'] = $value['ammount'];
					$unit['is_cancelled'] = 0;
					$unit['unit_type'] = $value['unit_type'];
					unset($value['id'], $value['ammount']);

					$reservationUnit = $this->ReservationUnits->newEntity();
					$reservationUnit = $this->ReservationUnits->patchEntity($reservationUnit, $unit);
					if (!$this->ReservationUnits->save($reservationUnit)) {
							$body = 'error';
							break;
						}else {
							$body = 'success';
						}
				}
			}

		}
		$this->response->body($body);
		$this->response->type('json');
		return $this->response;
	}

	public function quotation($idQuotation) {
		$this->autoRender = false;
		$this->loadModel('Quotations');
		$this->loadModel('Parks');
		$this->loadModel('Storages');
		$vendedor = $this->request->session()->read('Auth.User');
		$assets = parent::$assets;
		$uf = Cache::read('uf');

		$datos = $this->Quotations->get($idQuotation, ['contain' => [
			'Projects' => [
				'fields' => ['id', 'name', 'address', 'reservation_ammount', 'pie_percentage', 'region_id', 'commune_id', 'active', 'project_logo']
			],
			'Apartments' => ['ApartmentTypes', 'ApartmentFloor'],
			'Clients' => ['Regions'], 'Users', 'ReservationUnits']])->toArray();
			//pr($datos);die;

			if(!empty($datos['reservation_units'])){
				foreach ($datos['reservation_units'] as $key => $value) {
					if ($value['unit_type'] == 1) {
						$datos_park[$key] = $value;
						$valor_park = 0;
						$id_park = [];
						$num_park = [];


						foreach ($datos_park as $key => $value) {
							$id_park[$key] = $value['unit_id'];
							$valor_park = $value['reservation_ammount'] + $valor_park;
						}
						foreach ($id_park as $key => $value) {
							$num_park[$key] = $this->Parks->get($value, ['fields' => ['number', 'id']]);
						}

					}else if ($value['unit_type'] == 2){
						$datos_storage[$key] = $value;
						$valor_storage = 0;
						$id_storage = [];
						$num_storage = [];

						foreach ($datos_storage as $value_storage) {
							$id_storage[$key] = $value['unit_id'];
							$valor_storage = $value_storage['reservation_ammount'] + $valor_storage;
						}
						foreach ($id_storage as $key => $value) {
							$num_storage[$key] = $this->Storages->get($value, ['fields' => ['number', 'id']]);
						}
					}
				}
			}

		$this->set(compact('datos', 'num_park','num_storage', 'idQuotation', 'assets', 'uf', 'valor_park', 'valor_storage'));

		$root = ROOT;
		$path = str_replace('cake', '', $root) . 'assets' . DS . 'assets' . DS . 'files' . DS . 'quotations' . DS . 'cotizacion-' . $idQuotation . DS;
		$file = 'cotizacion-' . $idQuotation . '.pdf';

		$CakePdf = new CakePdf();
		$CakePdf->templatePath('Quotes');
		$CakePdf->template('quotation', 'default');
		$CakePdf->viewVars($this->viewVars);
		$pdf = $CakePdf->output();
		$pdf = $CakePdf->write($path . $file);

		$this->response->type = 'json';
		$this->response->body('success');
	}

	public function createReservation($id) {
		$this->autoRender = false;
		$this->loadModel('Reservations');
		$this->loadModel('Quotations');

		$reservation = $this->Reservations->find('all')
			->where(['quotation_id' => $id])
			->first();

		//pr($reservation);
		if (empty($reservation)) {
			$quotation = $this->Quotations->get($id);
			$reservation = $this->Reservations->newEntity();
			$reservation->client_id = $quotation->client_id;
			$reservation->project_id = $quotation->project_id;
			$reservation->apartment_id = $quotation->apartment_id;
			$reservation->user_id = $quotation->user_id;
			$reservation->quotation_id = $id;

			if($this->Reservations->save($reservation)) {
				return $reservation->id;
			} else {
				return false;
			}
		} else if(!empty($reservation)){

			return $reservation->id;

		} else {
			return false;
		}
	}

	public function myQuotes(){
		$assets = parent::$assets;
		$this->loadModel('Quotations');
		$cotizaciones = $this->Quotations->find('all')
			->contain(['Apartments', 'Clients', 'Projects', 'Reservations'])
			->where(['user_id' => $this->AuthUser->id()]);
		$this->set(compact('cotizaciones', 'assets'));
	}
}
?>
