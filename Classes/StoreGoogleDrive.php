<?php

namespace App\Classes\GoogleDrive;

use Exception;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive_ParentReference;

class StoreGoogleDrive
{
    #variables

    public function getClient()
    {
        try {
            $client = new Google_Client();
            $client->setClientId(env('GOOGLE_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
            $client->refreshToken(env('GOOGLE_DRIVE_REFRESH_TOKEN'));
            return $client;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function listFolder($folderId)
    {
        #conectar al cliente google
        $client = $this->getClient();
        #instanciar servicio drive
        $service = new Google_Service_Drive($client);
        #opciones
        $optParams = array(
            'q' => "'" . $folderId . "' in parents and trashed = false",
            'fields' => '*'
        );
        #listar Archivos
        $listFiles = $service->files->listFiles($optParams);
        #validar y organizar el listado
        $result = [];
        if (count($listFiles->getFiles()) != 0) {
            foreach ($listFiles->getFiles() as $file) {
                $result[] = [
                    'name' => $file->getName(),
                    'id' => $file->getId(),
                    'mimetype' => $file->getMimeType(),
                    'extension' => $this->getExtension($file->getMimeType()),
                    'parent' => $file->getParents()[0],
                    'weblink' => $file->webViewLink
                ];
            }
        }

        return $result;
    }

    public function listTreeFolder($folderId)
    {
        try {
            $listFiles = $this->listFolder($folderId);
            $result = [];
            if (count($listFiles) > 0) {
                
                foreach ($listFiles as $file) {
                    //var_dump($result);
                    if ($file['mimetype'] == 'application/vnd.google-apps.folder') {
                        //busca archivos en carpeta
                        $listFolder = $this->listFolder($file['id']);
                        $result['children'][] = ['id'=> $file['id'],'name'=>$file['name'],'extension'=>$file['extension'],'children'=>$this->listTreeFolder($file['id'])['children']];
                    } else {
                        $result['children'][] = $file;
                    }
                }
            }
            return $result;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function createFolder($nameFolder)
    {
        #conectar al cliente google
        $client = $this->getClient();
        #instanciar servicio drive
        $service = new Google_Service_Drive($client);
        #instanciar servicio de crear con el nombre de la carpeta
        $fileMetadata = new Google_Service_Drive_DriveFile(array(
            'name' => $nameFolder,
            'parents' => array(env('GOOGLE_DRIVE_FOLDER_ID')),
            'mimeType' => 'application/vnd.google-apps.folder'
        ));
        #Obtiene el id de la carpeta creada
        $folderId = $service->files->create($fileMetadata, array(
            'fields' => 'id'
        ));

        return $folderId->getId();
    }

    public function moveAllFolderTree($list_files, $folderId)
    {
        try {
            if (count($list_files) > 0) {
                foreach ($list_files as $file) {
                    if ($file['mimetype'] == 'application/vnd.google-apps.folder') {
                        //crear carpeta
                        $folderIdNew =  $this->createSubFolder($file['name'], $folderId);
                        //listar archivos
                        $listFolderNew = $this->listFolder($file['id']);

                        $this->moveAllFolderTree($listFolderNew, $folderIdNew['id']);
                    } else {
                        $this->moveFileToFolder($folderId, $file['id'], $x = substr($file['name'], 0, strrpos($file['name'], '.')));
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function moveFileToFolder($folderId, $fileId, $newFilename)
    {
        try {
            #conectar al cliente google
            $client = $this->getClient();
            #instanciar servicio drive
            $service = new Google_Service_Drive($client);

            #copiar archivo
            $copiedFile = new Google_Service_Drive_DriveFile(array(
                'parents' => array($folderId)
            ));

            $newFile = $service->files->copy($fileId, $copiedFile);

            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function createSubFolder($nameFolder, $folderId)
    {
        #conectar al cliente google
        $client = $this->getClient();
        #instanciar servicio drive
        $service = new Google_Service_Drive($client);
        #instanciar servicio de crear con el nombre de la carpeta
        $fileMetadata = new Google_Service_Drive_DriveFile(array(
            'name' => $nameFolder,
            'parents' => array($folderId),
            'mimeType' => 'application/vnd.google-apps.folder'
        ));
        #Obtiene el id de la carpeta creada
        $folderIdNew = $service->files->create($fileMetadata, array(
            'fields' => 'id'
        ));

        return $folderIdNew;
    }

    public function getExtension($mimetype = '')
    {
        $extension = "file";

        if (!$mimetype) {
            return $extension;
        }

        #extension Equivalente
        $extensionVal = [
            'application/pdf' => 'pdf',
            'application/msword' => 'word',
            'application/msword' => 'word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => 'word',
            'application/vnd.ms-word.document.macroEnabled.12' => 'word',
            'application/vnd.ms-word.template.macroEnabled.12' => 'word',
            'application/vnd.ms-excel' => 'excel',
            'application/vnd.ms-excel' => 'excel',
            'application/vnd.ms-excel' => 'excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => 'excel',
            'application/vnd.ms-excel.sheet.macroEnabled.12' => 'excel',
            'application/vnd.ms-excel.template.macroEnabled.12' => 'excel',
            'application/vnd.ms-excel.addin.macroEnabled.12' => 'excel',
            'application/vnd.ms-excel.sheet.binary.macroEnabled.12' => 'excel',
            'application/vnd.ms-powerpoint' => 'powerpoint',
            'application/vnd.ms-powerpoint' => 'powerpoint',
            'application/vnd.ms-powerpoint' => 'powerpoint',
            'application/vnd.ms-powerpoint' => 'powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.template' => 'powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => 'powerpoint',
            'application/vnd.ms-powerpoint.addin.macroEnabled.12' => 'powerpoint',
            'application/vnd.ms-powerpoint.presentation.macroEnabled.12' => 'powerpoint',
            'application/vnd.ms-powerpoint.template.macroEnabled.12' => 'powerpoint',
            'application/vnd.ms-powerpoint.slideshow.macroEnabled.12' => 'powerpoint',
            'application/vnd.ms-access' => 'mdb',
            'application/vnd.google-apps.audio' => 'audio',
            'application/vnd.google-apps.document' => 'word',
            'application/vnd.google-apps.file' => 'file',
            'application/vnd.google-apps.folder' => 'folder',
            'application/vnd.google-apps.photo' => 'image',
            'application/vnd.google-apps.presentation' => 'powerpoint',
            'application/vnd.google-apps.spreadsheet' => 'excel',
            'application/vnd.google-apps.video' => 'video',
            'image/jpeg' => 'image',
            'image/png' => 'image',
            'image/gif' => 'image',
            'video/mpeg' => 'video',
            'video/ogg' => 'video',
            'video/3gpp' => 'video',
            'video/x-msvideo' => 'video',
            'text/plain' => 'text',
            'text/csv' => 'excel',
            'text/html' => 'code'
        ];

        if (!array_key_exists($mimetype, $extensionVal)) {
            if (strpos($mimetype, 'text/') !== false) {
                $extension = 'text';
            } elseif (strpos($mimetype, 'video/') !== false) {
                $extension = 'video';
            } elseif (strpos($mimetype, 'audio/') !== false) {
                $extension = 'audio';
            } elseif (strpos($mimetype, 'font/') !== false) {
                $extension = 'font';
            } elseif (strpos($mimetype, 'application/') !== false) {
                $extension = 'application';
            } elseif (strpos($mimetype, 'image/') !== false) {
                $extension = 'image';
            }
        } else {
            $extension = $extensionVal[$mimetype];
        }

        return $extension;
    }
}
