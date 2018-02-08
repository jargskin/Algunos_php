<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use CakePdf\Pdf\CakePdf;
use Cake\Cache\Cache;


class RecaudationsController extends AppController {

	public function recaudations(){

		$this->loadModel('Regions');
		$this->loadModel('Projects');

		$regiones =  $this->Regions->find('all');

		if($this->request->is('post')){
			$proyecto = $this->Projects->get($this->request->data['project_id'], ['contain' =>
				['Promises' => [
					'ParkPromises' => [
						'Parks'
					],
					'StoragePromises' => [
						'Storages'
					],
					'Clients' => [
						'Communes',
						'Regions'
					],
					'Reservations' => [
						'Cuotones',
						'Apartments'
					]
				]
			]
		]
	);
		}
		$this->set(compact('proyecto', 'regiones'));

	}
	public function recaudationReserve($id = null) {
		$this->autoRender = false;
		$economicIndicator = Cache::read('uf');

		$root = ROOT;
		$path = str_replace('cake', '', $root) . 'assets' . DS . 'assets' . DS . 'files' . DS . 'Recaudacion' . DS . 'recaudacion-promesa-' . $id . DS;
		$file = 'recaudacion_promesa_' . $id . '.pdf';
		$fileDir = $path . $file;

		if (file_exists($fileDir)) {
			unlink($fileDir);
			rmdir($path);
		}

		if (!file_exists($fileDir)) {

			if(!is_dir($path)) {
				mkdir($path, 0777, true);
			}

			$this->viewBuilder()->layout('pdf/default');
			$this->loadModel('Promises');
			$this->loadModel('Reservations');
			$assets = parent::$assets;
			$promesa = $this->Promises->get($id, ['contain' => [
						'ParkPromises' => [
							'Parks' => ['ParkFloors']
						],
						'StoragePromises' => [
							'Storages' => ['StorageFloors']
						],
						'Projects',
						'Users',
						'Clients' => [
							'Communes',
							'Regions'
						],
						'Reservations' => [
							'Cuotones',
							'Apartments' => ['ApartmentTypes', 'ApartmentFloor']
						]
					]
				]
			);

			$reserva = $this->Reservations->get($promesa->reservation->id, ['contain' => [
				'Apartments' => ['ApartmentTypes'],
				'Clients',
				'Projects',
				'Promises' => [
					'ParkPromises' => ['Parks'],
					'StoragePromises'=> ['Storages']
				]
			]]);

			$bancos = [
				'Banco Bice',
				'Banco Condell',
				'Banco Consorcio',
				'Banco de Chile',
				'Banco de Crédito e Inversiones (BCI)',
				'Banco del Desarrollo',
				'Banco Edwards Citibank',
				'Banco Falabella',
				'Banco Internacional',
				'Banco Itau',
				'Banco Penta',
				'Banco Ripley',
				'Banco Santander Chile',
				'Banco Security',
				'BancoEstado',
				'BBVA',
				'Corpbanca',
				'Credichile',
				'HSBC',
				'Santander Banefe',
				'Scotiabank Sud Americano'
			];

			$this->set(compact('promesa', 'reserva', 'assets', 'bancos', 'economicIndicator'));

			$CakePdf = new CakePdf();
			$CakePdf->templatePath('Recaudations');
			$CakePdf->template('recaudationReserve', 'default');
			$CakePdf->viewVars($this->viewVars);
			$pdf = $CakePdf->write($path . $file);
		}
		$file_url = $path . $file;
		header('Content-Type: application/pdf');
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=Recaudacion-promesa-" . $id . '.pdf');
		readfile($file_url);

	}

	public function declarationRecord($id = null) {
		$this->autoRender = false;
		$root = ROOT;
		$path = str_replace('cake', '', $root) . 'assets' . DS . 'assets' . DS . 'files' . DS . 'Antecedentes' . DS . 'declaracion-antecedentes-' . $id . DS;
		$file = 'Declaracion-antecedentes' . $id . '.pdf';
		$fileDir = $path . $file;
		if (!file_exists($fileDir)) {
			mkdir($path, 0777, true);
		}

		$this->viewBuilder()->layout('pdf/default');
		$this->loadModel('Promises');
		$assets = parent::$assets;
		$promesa = $this->Promises->get($id, ['contain' => ['Clients', 'Projects']]);
		$this->set(compact('promesa', 'assets'));

		$CakePdf = new CakePdf();
		$CakePdf->templatePath('Recaudations');
		$CakePdf->template('declarationRecord', 'default');
		$CakePdf->viewVars($this->viewVars);
		$pdf = $CakePdf->write($path . $file);


		$file_url = $path . $file;
		header('Content-Type: application/pdf');
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=Declaracion-Antecedentes-" . $id . '.pdf');
		readfile($file_url);

	}
}
