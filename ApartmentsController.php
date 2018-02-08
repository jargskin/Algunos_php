<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;


class ApartmentsController extends AppController {
	public function index() {

		$this->loadModel('Regions');
		$this->loadModel('Apartments');

		$regiones = $this->Regions->find('all')->toArray();
		$departamentos = $this->Apartments->find('all')->contain(['Projects' => [
			'fields' => ['id', 'name', 'address', 'reservation_ammount', 'pie_percentage', 'region_id', 'commune_id', 'active', 'project_logo']
		],'ApartmentFloor','ApartmentTypes'])->toArray();

		$data = $this->request->data;

		if ($this->request->is('post') && $data['filtro'] == 'filtro') {

			$departamentos = $this->filtrar($this->request->data);
		}

		$this->set(compact('departamentos', 'regiones'));
	}
	function filtrar($data) {
		$this->loadModel('Apartments');

		$params = $data;
		$dpto_filtrado = $this->Apartments->find('all')
			->contain(['Projects' => [
				'fields' => ['id', 'name', 'address', 'reservation_ammount', 'pie_percentage', 'region_id', 'commune_id', 'active', 'project_logo']
			],'ApartmentFloor','ApartmentTypes'])
			->where(['Apartments.project_id' => $params['project_id']])->toArray();
		return $dpto_filtrado;
	}

	public function add() {
		$this->loadModel('Apartments');
		$this->loadModel('ApartmentFloor');
		$this->loadModel('Regions');
		$this->loadModel('Projects');

		$regiones = $this->Regions->find('all');
		$pisos = $this->ApartmentFloor->find('all');
		$proyectos = $this->Projects->find('all');

		$this->set(compact('regiones', 'pisos', 'proyectos'));

		$departamento = $this->Apartments->newEntity();
		if ($this->request->is('post')) {
			if ($this->request->data['files']['error'] == 0) {
				$imagen = $this->uploadFile($this->request->data['files']);
			}
			if (!empty($imagen)){
				$data = [
					'project_id' => $this->request->data['project_id'],
					'number' => $this->request->data['number'],
					'm2_interior' => $this->request->data['m2_interior'],
					'm2_terrace' => $this->request->data['m2_terrace'],
					'm2_gard_terr' => $this->request->data['m2_gard_terr'],
					'apartment_floor_id' => $this->request->data['apartment_floor_id'],
					'dorms' => $this->request->data['dorms'],
					'baths' => $this->request->data['baths'],
					'orientation' => $this->request->data['orientation'],
					'apartment_type_id' => $this->request->data['apartment_type_id'],
					'price' => $this->request->data['price'],
					'apartment_image' => $imagen['apartment_image'],
					'apartment_dir' => $imagen['apartment_dir']
				];
			}else {
				$data = [
					'project_id' => $this->request->data['project_id'],
					'number' => $this->request->data['number'],
					'm2_interior' => $this->request->data['m2_interior'],
					'm2_terrace' => $this->request->data['m2_terrace'],
					'm2_gard_terr' => $this->request->data['m2_gard_terr'],
					'apartment_floor_id' => $this->request->data['apartment_floor_id'],
					'dorms' => $this->request->data['dorms'],
					'baths' => $this->request->data['baths'],
					'orientation' => $this->request->data['orientation'],
					'apartment_type_id' => $this->request->data['apartment_type_id'],
					'price' => $this->request->data['price'],
					'apartment_image' => null,
					'apartment_dir' => null
				];
			}
			$departamento =  $this->Apartments->patchEntity($departamento, $data);
			if ($this->Apartments->save($departamento)) {
				$this->Flash->success('Departamento creado con exito');
			}
			else {
				$this->Flash->error('Ha ocurrido un Error, Favor revisa la informacíón');
			}
		}
	}

	function uploadFile($imagen){
		$filesWithError = [];
		$root = ROOT;
		$filesDir = str_replace('cake', '', $root) . 'assets' . DS . 'assets' . DS .'files' . DS .'images';

		$fileDir = $filesDir . DS . 'apartments';
		$fileName = 'Depto' . date('dmYHis') . '.' .str_replace('image/', '', $imagen['type']);
		$fileData = [
			'apartment_image' => $fileName,
			'apartment_dir' => $fileDir . DS . $fileName
		];

		if (!file_exists($fileDir)) {
			mkdir($fileDir, 0777, true);
		}

		if (move_uploaded_file($imagen['tmp_name'], $fileDir . DS . $fileName)){
		}

		return $fileData;
	}

	public function edit($id){
		$this->loadModel('Apartments');
		$this->loadModel('ApartmentFloor');
		$this->loadModel('ApartmentTypes');
		$this->loadModel('Regions');
		$this->loadModel('Projects');

		$regiones = $this->Regions->find('all');
		$pisos = $this->ApartmentFloor->find('all');
		$tipos = $this->ApartmentTypes->find('all');
		$proyectos = $this->Projects->find('all');
		$departamento = $this->Apartments->get($id, ['contain' => ['ApartmentTypes', 'ApartmentFloor', 'Projects']]);

		if ($this->request->is('post')) {
			if ($this->request->data['files']['error'] == 0) {
				$imagen = $this->uploadFile($this->request->data['files']);
			}
			$data = [
				'project_id' => $this->request->data['project_id'],
				'number' => $this->request->data['number'],
				'm2_interior' => $this->request->data['m2_interior'],
				'm2_terrace' => $this->request->data['m2_terrace'],
				'm2_gard_terr' => $this->request->data['m2_gard_terr'],
				'apartment_floor_id' => $this->request->data['apartment_floor_id'],
				'dorms' => $this->request->data['dorms'],
				'baths' => $this->request->data['baths'],
				'orientation' => $this->request->data['orientation'],
				'apartment_type_id' => $this->request->data['apartment_type_id'],
				'price' => $this->request->data['price']
			];
			if (!empty($imagen)){
				$data = [
					'apartment_image' => $imagen['apartment_image'],
					'apartment_dir' => $imagen['apartment_dir']
				];
			}else {
				$data = [
					'apartment_image' => null,
					'apartment_dir' => null
				];
			}

			$departamento = $this->Apartments->patchEntity($departamento, $data);
			if ($this->Apartments->save($departamento)) {
				$this->Flash->success('Departamento editado con exito');
			}
			else {
				$this->Flash->error('Ha ocurrido un Error, Favor revisa la informacíón');
			}
		}

		$this->set(compact('departamento', 'regiones', 'pisos', 'tipos', 'proyectos'));
	}


	public function apartments() {
		$this->viewBuilder()->layout('false');
		$this->autoRender = false;

		$this->loadModel('Apartments');
		$query = [
			'reserved' => 0
		];
		$params = $this->request->getQueryParams();
		if(!empty($params['project']) && $params['project'] !== "") {
			$query['Projects.id'] = $params['project'];
		}
		if(!empty($params['orientation']) && $params['orientation'] !== "") {
			$query['orientation'] = $params['orientation'];
		}
		if(!empty($params['baths']) && $params['baths'] !== "") {
			$query['baths'] = $params['baths'];
		}
		if(!empty($params['apartment_floor']['number']) && $params['apartment_floor']['number'] !== "") {
			$query['ApartmentFloor.number'] = $params['apartment_floor']['number'];
		}
		if(!empty($params['apartment_type']['name']) && $params['apartment_type']['name'] !== "") {
			$query['ApartmentTypes.name'] = $params['apartment_type']['name'];
		}
		if(!empty($params['dorms']) && $params['dorms'] !== "") {
			$query['dorms'] = $params['dorms'];
		}
		if(!empty($params['price']) && $params['price'] !== "") {
			$query['price'] = $params['price'];
		}
		if(!empty($params['number']) && $params['number'] !== "") {
			$query['Apartments.number'] = $params['number'];
		}
		if(!empty($params['m2_interior']) && $params['m2_interior'] !== "") {
			$query['m2_interior'] = $params['m2_interior'];
		}
		if(!empty($params['m2_terrace']) && $params['m2_terrace'] !== "") {
			$query['m2_terrace'] = $params['m2_terrace'];
		}

		$departamentos = $this->Apartments->find('all')
		->where($query)
		->contain(['Projects' => [
			'fields' => ['id', 'name', 'address', 'reservation_ammount', 'pie_percentage', 'region_id', 'commune_id', 'active', 'project_logo']
		],'ApartmentFloor','ApartmentTypes'])
		->toArray();

		$json_departamentos = json_encode($departamentos);
		$this->response->type('json');
		$this->response->body($json_departamentos);
		return $this->response;
	}

	function getType() {
		$this->viewBuilder()->layout('false');
		$this->autoRender = false;
		$this->loadModel('ApartmentTypes');

		if ($this->request->is('get')){
			$params = $this->request->getQueryParams();

			$pisos =  $this->ApartmentTypes->find('all')
				->where(['project_id' => $params['seleccion']]);
		}

		$json = json_encode($pisos);
		$this->response->type('json');
		$this->response->body($json);
		return $this->response;

	}

	public function bulkEditPricesApartment($idProject){
		$this->viewBuilder()->layout('false');
		$this->autoRender = false;
		$this->loadModel('Apartments');
		$error = false;

		if($this->request->is('post')){
			if($this->request->data != null){
				$params = $this->request->data;
				//pr($params['price']['depto']);
				foreach ($params['price']['depto'] as $key => $value) {
					$depto = $this->Apartments->get($key);
					$data['price'] = $value;

					$depto = $this->Apartments->patchEntity($depto, $data);
					if (!$this->Apartments->save($depto)) {
						$error = true;
					}
				}
				if (!$error) {
					$this->Flash->success('Precios Actualizados con éxito');
					$this->redirect(['controller' => 'projects', 'action' => 'prices' , $idProject]);
				}else {
					$this->Flash->error('Ha ocurrido un error, Favor revisa la Información');
					$this->redirect(['controller' => 'projects', 'action' => 'prices' , $idProject]);
				}
			}
		}
	}

	public function editPriceApartment(){
		$this->viewBuilder()->layout('false');
		$this->autoRender = false;
		$this->loadModel('Apartments');
		$body = 'error';

		if($this->request->is('get')){
				$params = $this->request->getQueryParams();
				$depto = $this->Apartments->get($params['id']);
				$data['price'] = $params['price'];

				$depto = $this->Apartments->patchEntity($depto, $data);
				if ($this->Apartments->save($depto)) {
					$body = 'success';
				}

				$json_apartment = json_encode($body);
				$this->response->type('json');
				$this->response->body($json_apartment);
				return $this->response;
		}
	}


}

?>
