<?php
class SkarbReviews
{
    public $modx;
    public $config = [];

    function __construct(modX &$modx, array $config = [])
    {
        $this->modx         =& $modx;
        $this->pdoTools     =  $this->modx->getService('pdoFetch');
        $this->modx->lexicon->load($this->modx->cultureKey . ':skarb:error');
        $this->modx->loadClass('skarbanswer', $this->modx->getOption('core_path') . 'components/skarb/api/', true, true);
        $this->modx->loadClass('skarbclear', $this->modx->getOption('core_path') . 'components/skarb/api/', true, true);
        $this->modx->loadClass('skarbshortlink', $this->modx->getOption('core_path') . 'components/skarb/api/', true, true);
        $this->modx->loadClass('skarbupload', $this->modx->getOption('core_path') . 'components/skarb/api/', true, true);
        $this->modx->loadClass('skarbpermission', $this->modx->getOption('core_path') . 'components/skarb/api/', true, true);
        $this->SkarbAnswer     = new SkarbAnswer();
        $this->SkarbClear      = new SkarbClear($this->modx);
        $this->SkarbShortLink  = new SkarbShortLink($this->modx);
        $this->SkarbUpload     = new SkarbUpload($this->modx);
        $this->SkarbPermission = new SkarbPermission($this->modx);
        
        $this->user_id      =  ($this->modx->user->id > 0) ? (int) $this->modx->user->id : (string) $_SESSION['skarbSiteUser'];
        $this->user_anonim  =  (gettype($this->user_id) == 'integer') ? false : true;
        $this->user_admin   =  ($this->user_anonim) ? false : $this->SkarbPermission->UserAllow([1, 16]);
        $this->tmp_dir      =  MODX_BASE_PATH . 'assets/inc/tmp/reviews/' . $this->user_id . '/';
        $this->file_dir     =  MODX_BASE_PATH . 'assets/inc/info/company/';
        $this->avatar_dir   =  'assets/katalog/images/avatar/vovimage/';
    }    
    
    
    
    
    


    public function getTplReviewsCompanySk($array = []) 
    {
        if (!isset($array['item_id'])) $array['item_id'] = $this->modx->resource->id;
        if ($id_company = $this->SkarbClear->isExistsCompany(preg_replace('/[^0-9]/', '', $array['item_id']))) return $this->getListReviewsSk(1, $id_company, $array);
    }
    
    




    //get id reviews
    public function getListIdReviewsSk($type = 1, $item_id = 0, $limit = 30, $offset = 0, $list_parent = []) 
    { 
        $list_id = [];
        
        if (!empty($list_parent)) {
            $sql = $this->modx->query('SELECT id FROM ' . $this->modx->getTableName('SkarbReview') . ' WHERE thread IN (' . implode(',', $list_parent) . ') AND type = ' . $type . ' AND item_id = ' . $item_id . ' AND hidden = 0 ORDER BY createdon DESC');
        } else {
            $sql = $this->modx->query('SELECT id FROM ' . $this->modx->getTableName('SkarbReview') . ' WHERE parent = 0 AND type = ' . $type . ' AND item_id = ' . $item_id . ' AND hidden = 0 ORDER BY updatedon_thread DESC LIMIT ' . $offset . ', ' . $limit);
        }
        
        if ($result = $sql->fetchAll(PDO::FETCH_ASSOC)) {
            foreach ($result as $comment) {
                $list_id[] = $comment['id'];
            }
        }
        
        return $list_id;
    }





    //get list likes
    public function getListLikesReviewsSk($list_id_review = []) 
    { 
        $list_likes = [];
        $q = $this->modx->newQuery('SkarbLike');
        $q->select('SkarbLike.item_id, SkarbLike.votes_up, SkarbLikeVotes.like_id');
        if ($this->user_anonim) {
            $q->leftJoin('SkarbLikeVotes', 'SkarbLikeVotes', 'SkarbLikeVotes.like_id = SkarbLike.id AND SkarbLikeVotes.user_key = \'' . $this->user_id . '\'');
        } else {
            $q->leftJoin('SkarbLikeVotes', 'SkarbLikeVotes', 'SkarbLikeVotes.like_id = SkarbLike.id AND SkarbLikeVotes.user_id = ' . $this->user_id);
        }
        $q->where([
            'SkarbLike.type' => 2, 
            'SkarbLike.item_id:IN' => $list_id_review, 
        ]);
        $q->limit(0, 0);
        $q->sortby('SkarbLike.id', 'DESC');
        $result_likes = ($q->prepare() && $q->stmt->execute()) ? $q->stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        
        foreach ($result_likes as $like) {
            $list_likes[$like['item_id']] = [
                'votes_up' => $like['votes_up'],
                'voted' => ($like['like_id']) ? true : false,
            ];
        }
        
        return $list_likes;
    }

    


    //get list reviews
    public function getListReviewsSk($type = 1, $item_id = 0, $array = []) 
    {       
        $limit = (!empty($array['limit'])) ? preg_replace('/[^0-9]/', '', $array['limit']) : 30;
        $offset = (!empty($array['offset'])) ? preg_replace('/[^0-9]/', '', $array['offset']) : 0;
        $item_id = preg_replace('/[^0-9]/', '', $item_id);

        if ($limit > 50) $limit = 50;
        $output = ['limit' => $limit, 'item_id' => $item_id, 'count_comments' => 0];
        
        $list_parent = $this->getListIdReviewsSk($type, $item_id, $limit, $offset);

        //имя анонима
        if ($this->user_anonim) {
            if (!isset($_COOKIE['skarbAnonimUserName']) || empty($_COOKIE['skarbAnonimUserName'])) {
                $output['anonim_user_name'] = $this->getRandomUsernameSk();
                setcookie('skarbAnonimUserName', $output['anonim_user_name'], time() + 21474836, '/');
            } else {
                $output['anonim_user_name'] = stripslashes($_COOKIE['skarbAnonimUserName']);
            }
        }
        
        if (!empty($list_parent)) {
            $list_child = $this->getListIdReviewsSk($type, $item_id, 0, 0, $list_parent);
            $list_id_review = array_merge($list_parent, $list_child);            
            
            $q = $this->modx->newQuery('SkarbReview');
            $q->select('SkarbReview.id, SkarbReview.createdon, SkarbReview.updatedon, SkarbReview.deletedon, SkarbReview.deletedby, SkarbReview.value, SkarbReview.thread, SkarbReview.parent, SkarbReview.user_id, SkarbReview.user_key, SkarbReview.user_name, SkarbReviewPhoto.photo, SkarbReview.updatedon_thread');
            $q->leftJoin('SkarbReviewPhoto', 'SkarbReviewPhoto', 'SkarbReviewPhoto.id = SkarbReview.user_photo AND SkarbReview.user_photo > 0');
            $q->where([
                'SkarbReview.id:IN' => $list_id_review, 
            ]);
            $q->limit(0, 0);
            $q->sortby('SkarbReview.updatedon_thread', 'DESC');
            $q->sortby('SkarbReview.id', 'DESC');
            
            $result_comments = ($q->prepare() && $q->stmt->execute()) ? $q->stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($result_comments as $comment) {
                $comment['createdon_format'] = date('d.m.Y', $comment['createdon']);
                $comment['createdon_ago'] = $this->SkarbClear->showDateAgoSk($comment['createdon'], false);
                
                if (isset($_COOKIE['skarbHiddenReviews']) && !empty($_COOKIE['skarbHiddenReviews'])) {
                    $list_hidden_reviews = json_decode($_COOKIE['skarbHiddenReviews'], 1);
                    if (is_array($list_hidden_reviews) && in_array($comment['id'], $list_hidden_reviews)) $comment['hidden_review'] = true;
                }
                
                $path_files = $this->file_dir . $item_id . '/reviews/' . $comment['id'] . '/img/';

                if (file_exists($path_files)) {
                    $it = new FilesystemIterator($path_files);
                    foreach ($it as $fileinfo) {
                        if (is_file($fileinfo)) {
                            $idx = explode('-skarb-', $fileinfo->getFilename())[0];
                            $comment['files'][$idx]['filename'] = $fileinfo->getFilename();
                        }
                    }
                    if (!empty($comment['files'])) ksort($comment['files']);
                }
                $comment['path_files'] = str_replace(MODX_BASE_PATH, '', $path_files);
                
                
                $file_text = $this->file_dir . $item_id . '/reviews/' . $comment['id'] . '/text/' . $comment['value'] . '.txt';    
                if (file_exists($file_text)) $comment['text'] = file_get_contents($file_text);
                
                if ((time() - $comment['createdon']) < 3600) {
                    if ($this->user_anonim && $this->user_id == $comment['user_key'] || !$this->user_anonim && $this->user_id == $comment['user_id'])  $comment['edit_enable'] = true;                    
                } elseif ($this->user_admin) {
                    $comment['edit_enable'] = true;
                }
                
                if ($comment['user_id'] > 0) {
                    $users[] = $comment['user_id'];
                } else {
                    $comment['user_id'] = '0' . strrev($comment['id']);
                    $output['list_users'][$comment['user_id']] = 
                    [
                        'photo' => $this->avatar_dir . $comment['photo'] . '_30x30.jpg',
                        'user_name' => stripslashes($comment['user_name']),
                    ];
                }

                if (!empty($comment['thread'])) {
                    $output['list_childs'][$comment['thread']][$comment['id']] = $comment;
                } else {
                    $output['list_comments'][$comment['id']] = $comment;
                }
            }
            
            if (isset($users)) {
                $q = $this->modx->newQuery('modUser');
                $q->select('modUser.id, modUser.username, modUserProfile.photo, modUserProfile.fullname, VovCompanyWorkers.post');
                $q->leftJoin('modUserProfile', 'modUserProfile', 'modUserProfile.internalKey = modUser.id');
                $q->leftJoin('VovCompanyWorkers', 'VovCompanyWorkers', 'VovCompanyWorkers.uid = modUser.id AND VovCompanyWorkers.rang IN (1,3,9) AND VovCompanyWorkers.rid = ' . $item_id);
                $q->where([
                    'modUser.id:IN' => $users
                ]);
                $q->limit(0, 0);
                $q->groupby('modUser.id');
                $result_users = ($q->prepare() && $q->stmt->execute()) ? $q->stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                
                foreach ($result_users as $user) {                    
                    $output['list_users'][$user['id']] = 
                    [
                        'photo' => $user['photo'],
                        'user_name' => (!empty($user['fullname'])) ? $user['fullname'] : $user['username'],
                        'post' => $user['post']
                    ];
                }
            }
            
            if (!empty($output['list_childs'])) {
                foreach ($output['list_childs'] as $key => $child) {
                    ksort($child);
                    $tmp_list_childs[$key] = $child;
                }
                $output['list_childs'] = $tmp_list_childs;
            }
            
            $output['count_comments'] = count($output['list_comments']);
            $output['list_files']     = $this->getListFilesReviewsSk($item_id);
            $output['list_likes']     = $this->getListLikesReviewsSk($list_id_review);            
        }
        
        return $output;
    }
    







    //update review
    public function updateReviewCompanySk($array = []) 
    {
        $updated = false;
        $q = $this->modx->newQuery('SkarbReview');
        $q->where(['SkarbReview.id' => $array['review_id']]);
        if ($this->user_anonim) {
            $q->where(['SkarbReview.user_key' => $this->user_id]);
        } else {
            $q->where(['SkarbReview.user_id' => $this->user_id]);
        }
    
        if (!$reviewObj = $this->modx->getObject('SkarbReview', $q)) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_permission'), NULL);
        if (!(time() - $reviewObj->get('createdon')) > 3600 && !$this->user_admin) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_review_edit_disable'), NULL);

        if ($this->user_anonim) {
            $clean_name_json = $this->cleanNameReviewCompanySk($array['name']);
            $clean_name = json_decode($clean_name_json, 1);
            if (!$clean_name['success']) {
                return $clean_name_json;
            } else {    
                if ($clean_name['data']['user_name'] != $reviewObj->get('user_name')) {
                    $updated = true;
                    $reviewObj->set('user_name', $clean_name['data']['user_name']);
                    $reviewObj->set('user_photo', $clean_name['data']['user_photo']);
                    
                    $clean_name['data']['user_name'] = stripslashes($clean_name['data']['user_name']);
                    $clean_name['data']['url_user_photo'] = $this->avatar_dir . $clean_name['data']['url_user_photo'] . '_30x30.jpg';
                }
            }
        }
        
        if (!$updated) {
            $clean_name['data']['user_name'] = null;
            $clean_name['data']['url_user_photo'] = null;
        }
        
        $dir = $this->file_dir . $reviewObj->get('item_id') . '/reviews/' . $reviewObj->get('id') . '/';    
        $dir_text = $dir . 'text/';
        $dir_img = $dir . 'img/';
        $dir_img_thumb = $dir_img . 'thumb/150x90/';
        
        $array['default_text'] = $array['text'];
        $clean_text_json = $this->cleanTextReviewCompanySk($array['text']);
        $clean_text = json_decode($clean_text_json, 1);
    
        if (!$clean_text['success']) {
            return $clean_text_json;
        } else {
            $array['text'] = $clean_text['message'];
            if(!file_exists($dir_text)) mkdir($dir_text, 0755, true); 
            $filename = 'uid' . md5(time()) . '.txt';
            
            file_put_contents($dir_text . $filename, $array['text']);
            $hash_new = hash_file('md5', $dir_text . $filename);
            if ($hash_new != $reviewObj->get('value')) {
                $reviewObj->set('value', $hash_new);
                $updated = true;
                rename($dir_text . $filename, $dir_text . $hash_new . '.txt');
                file_put_contents($dir_text . 'default-' . $hash_new . '.txt', $array['default_text']);
            } else {
                unlink($dir_text . $filename);
            }
        }
        
        $array['list_filename_front'] = (!empty($array['list_filename_front'])) ? json_decode($array['list_filename_front'], 1) : [];

        if (empty($array['list_filename_front'])) {
            if (empty($array['text'])) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_review_empty_comment'), NULL);
        } else {
            if(count($array['list_filename_front']) > 10) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_limit_img'), NULL);

            // list new files
            $list_tmp_files = $this->getListFilesReviewsSk($reviewObj->get('item_id'));
            if (isset($list_tmp_files['files'])) {
                if(!file_exists($dir_img)) mkdir($dir_img, 0755, true); 
                if(!file_exists($dir_img_thumb)) mkdir($dir_img_thumb, 0755, true); 
              
                foreach ($list_tmp_files['files'] as $filename) {
                    $updated = true;
                    rename(MODX_BASE_PATH . $list_tmp_files['path'] . $filename['filename'], $dir_img . $filename['filename']);
                    rename(MODX_BASE_PATH . $list_tmp_files['path'] . 'thumb/150x90/' . $filename['filename'], $dir_img_thumb . $filename['filename']);
                }
            }
        }


        if (file_exists($dir_img)) {
            $iterator = new RecursiveDirectoryIterator($dir_img);
            $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
            
            foreach ($files as $file) {
                if (!is_dir($file) && !in_array($file->getFilename(), $array['list_filename_front'])) {
                    unlink($file->getPathname());
                    $updated = true;
                }
            }
        }

        if ($updated) {
            if ((time() - $reviewObj->get('createdon')) > 600) $reviewObj->set('updatedon', time());
            $reviewObj->save();
        }
        
        $this->removeDirRecursiveSk($this->tmp_dir);
        
        return $this->SkarbAnswer->ReturnAnswer(true, 'success_update_review', '', ['updated' => $updated, 'fields' => ['review_id' => $array['review_id'], 'text' => $array['text'], 'path' => str_replace(MODX_BASE_PATH, '', $dir_img), 'list_filename_front' => $array['list_filename_front'], 'user_name' => $clean_name['data']['user_name'], 'user_photo' => $clean_name['data']['url_user_photo']]]);
    }
    
    
    
    
    
    
    
    
    //create review
    public function addReviewCompanySk($arr = []) 
    {
        if (empty($this->user_id)) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_default'), NULL);
        if (!empty($arr['review_id']) && $arr['review_id'] == preg_replace('/[^0-9]/', '', $arr['review_id'])) return $this->updateReviewCompanySk($arr);
        
        if ($item_id = $this->SkarbClear->isExistsCompany(preg_replace('/[^0-9]/', '', $arr['item_id']))) {
            if (!empty($arr['parent']) && $arr['parent'] == preg_replace('/[^0-9]/', '', $arr['parent'])) {
                $sql = $this->modx->query('SELECT thread FROM ' . $this->modx->getTableName('SkarbReview') . ' WHERE hidden = 0 AND deletedon IS NULL AND id = ' . $arr['parent']);
                if ($result = $sql->fetch(PDO::FETCH_ASSOC)) {
                    $arr['thread'] = ($result['thread']) ? $result['thread'] : $arr['parent'];
                } else {
                    return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_parent_review_removed'), NULL);
                }
            } else {
                $arr['parent'] = $arr['thread'] = 0;
            }
            
            if ($this->user_anonim) {
                $clean_name_json = $this->cleanNameReviewCompanySk($arr['name']);
                $clean_name = json_decode($clean_name_json, 1);
                if (!$clean_name['success']) {
                    return $clean_name_json;
                } else {
                    $arr['name'] = $clean_name['data']['user_name'];
                    $arr['user_photo'] = $clean_name['data']['user_photo'];
                    $arr['url_user_photo'] = $this->avatar_dir . $clean_name['data']['url_user_photo'] . '_30x30.jpg';
                }
            }
            
            $arr['default_text'] = $arr['text'];

            $clean_text_json = $this->cleanTextReviewCompanySk($arr['text']);
            $clean_text = json_decode($clean_text_json, 1);
            if (!$clean_text['success']) {
                return $clean_text_json;
            } else {
                $arr['text'] = $clean_text['message'];
            }
            
            $list_files = $this->getListFilesReviewsSk($item_id);
            
            if (isset($list_files['files']) && count($list_files['files']) > 10) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_limit_img'), NULL);
            if (!isset($list_files['files']) && empty($arr['text'])) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_review_empty_comment'), NULL);
            
            if (!empty($arr['text'])) {
                $tmp_dir = $this->tmp_dir . $item_id . '/img/';
                if(!file_exists($tmp_dir)) mkdir($tmp_dir, 0755, true); 
                
                $filename = 'uid' . md5(time()) . '.txt';
                file_put_contents($tmp_dir . $filename, $arr['text']);
                $hash_new = hash_file('md5', $tmp_dir . $filename);
            }
            
            if ($reviewObj = $this->modx->newObject('SkarbReview')) {
                $reviewObj->set('type', 1);
                $reviewObj->set('item_id', $item_id);
                
                if (!empty($arr['parent'])) {
                    $reviewObj->set('parent', $arr['parent']);
                    $reviewObj->set('thread', $arr['thread']);
                    $this->modx->exec('UPDATE ' . $this->modx->getTableName('SkarbReview') . ' SET updatedon_thread = ' . time() . ' WHERE id = ' . $arr['thread']);
                }
                if (isset($hash_new)) {
                    $reviewObj->set('value', $hash_new);
                }
                if (!$this->user_anonim) {
                    $reviewObj->set('user_id', $this->modx->user->id);
                } else {
                    $reviewObj->set('user_photo', $arr['user_photo']);
                    $reviewObj->set('user_name', $arr['name']);
                    $reviewObj->set('user_key', $_SESSION['skarbSiteUser']);
                }
                $reviewObj->set('ip', $this->SkarbClear->GetClientIp());
                $reviewObj->set('createdon', time());
                $reviewObj->set('updatedon_thread', time());
                $reviewObj->save();
                
                $dir = $this->file_dir . $item_id . '/reviews/' . $reviewObj->get('id') . '/';    
                $dir_text = $dir . 'text/';
                $dir_img = $dir . 'img/';
                
                if (isset($hash_new)) {
                    if(!file_exists($dir_text)) mkdir($dir_text, 0755, true); 
                    rename($tmp_dir . $filename, $dir_text . $hash_new . '.txt');
                    file_put_contents($dir_text . 'default-' . $hash_new . '.txt', $arr['default_text']);
                }
                
                $comment = $reviewObj->toArray();
                
                if (isset($list_files['files'])) {
                    if(!file_exists($dir_img)) mkdir($dir_img, 0755, true); 
                    rename(MODX_BASE_PATH . $list_files['path'], $dir_img);
                    $comment['files'] = $list_files['files'];
                    $comment['path_files'] = str_replace(MODX_BASE_PATH, '', $dir_img);
                }
                
                $comment['createdon_format'] = date('d.m.Y', $comment['createdon']);
                $comment['createdon_ago'] = 'только что';
                $comment['text'] = $arr['text'];
                
                if (!$this->user_anonim) {
                    $comment['user_id'] = $this->modx->user->id;
                    $profile = $this->modx->user->getOne('Profile');
                    $user[$comment['user_id']] = 
                    [
                        'photo' => $profile->get('photo'),
                        'user_name' => (!empty($profile->get('fullname'))) ? $profile->get('fullname') : $profile->get('username')
                    ];
                } else {
                    $comment['user_id'] = '0' . strrev($comment['id']);
                    $user[$comment['user_id']] = 
                    [
                        'photo' => $arr['url_user_photo'],
                        'user_name' => stripslashes($comment['user_name']),
                    ];
                }
                $comment['edit_enable'] = true;
                
                $tpl = $this->pdoTools->getChunk('@FILE chunks/reviews/tpl_line_review.html', [
                    'users' => $user,
                    'comments' => [0 => $comment],
                ]);
            
                $this->removeDirRecursiveSk($this->tmp_dir);
                
                $this->modx->exec('UPDATE ' . $this->modx->getTableName('modResource') . ' SET count_reviews = count_reviews + 1 WHERE id = ' . $item_id);

                return $this->SkarbAnswer->ReturnAnswer(true, 'success_create_reviews', '', ['item_id' => $item_id, 'id' => $comment['id'], 'parent' => $comment['parent'], 'thread' => $comment['thread'], 'tpl' => $tpl]);
            }
        }
        
        return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_default'), NULL);
    }







    //get form to edit a review
    public function getEditReview($array = []) 
    {
        $array['review_id'] = preg_replace('/[^0-9]/', '', $array['review_id']);
        if (empty($array['review_id'])) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_default'), NULL);
        
        $q = $this->modx->newQuery('SkarbReview');
        $q->select('SkarbReview.id, SkarbReview.item_id, SkarbReview.createdon, SkarbReview.value, SkarbReview.thread, SkarbReview.parent, SkarbReview.user_id, SkarbReview.user_key, SkarbReview.user_name');
        $where['SkarbReview.id'] = $array['review_id'];
        if (!$this->user_admin) {
            $where['SkarbReview.createdon:>='] = time() - 3600;
            if ($this->user_anonim) {
                $where['SkarbReview.user_key'] = $this->user_id;
            } else {
                $where['SkarbReview.user_id'] = $this->user_id;
            }
        } else {
            $where['SkarbReview.createdon:<'] = time() - 3600;
        }
        
        $q->where($where);
        $q->sortby('SkarbReview.id', 'DESC');
        $result_comment = ($q->prepare() && $q->stmt->execute()) ? $q->stmt->fetch(PDO::FETCH_ASSOC) : [];
        
        if (!empty($result_comment)) {    
            if (!empty($result_comment['user_name'])) $result_comment['user_name'] = stripslashes($result_comment['user_name']);

            $result_comment['list_files'] = $this->getListFilesReviewsSk($result_comment['item_id'], $result_comment['id'], false);            
            
            $file_text = $this->file_dir . $result_comment['item_id'] . '/reviews/' . $result_comment['id'] . '/text/default-' . $result_comment['value'] . '.txt';    
            if (file_exists($file_text)) {
                $result_comment['text'] = file_get_contents($file_text);
            }
            
            $tmp_dir = $this->tmp_dir . $result_comment['item_id'] . '/img/';
            if (file_exists($tmp_dir)) {
                $iterator = new RecursiveDirectoryIterator($tmp_dir);
                $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
                foreach ($files as $file) {
                    if (!is_dir($file)) unlink($file->getPathname());
                }
            }
            return $this->SkarbAnswer->ReturnAnswer(true, 'success_get_reviews', '', $result_comment);
        }
        
        return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_review_edit_disable'), NULL);
    }

    
    
    
    
    
    // get array reviews to activetape
    public function getActiveTapeSk($array = []) 
    {            
        $array['limit'] = (!empty($array['limit'])) ? preg_replace('/[^0-9]/', '', $array['limit']) : 10;
        $array['offset'] = (!empty($array['offset'])) ? preg_replace('/[^0-9]/', '', $array['offset']) : 0;
        $array['id_city'] = (!empty($array['id_city'])) ? preg_replace('/[^0-9]/', '', $array['id_city']) : 0;
        $array['user_id'] = (isset($array['user_id']) && !empty($array['user_id'])) ? preg_replace('/[^0-9]/', '', $array['user_id']) : 0;
        $array['user_anonim'] = (isset($array['user_anonim']) && !empty($array['user_anonim'])) ? true : false;

        if ($array['limit'] > 50) $array['limit'] = 50;

        $array['show_createdon'] = (isset($array['show_createdon']) && !empty($array['show_createdon'])) ? 1 : 0;

        if ($array['user_anonim']) {
            $query = $this->modx->newQuery('SkarbReview', [
                'id' => $array['user_id'], 
                'user_id' => 0,
            ]);
            $query->select('SkarbReview.user_name, SkarbReview.user_key');
            $query->limit(1, 0);
            $reviews_info = ($query->prepare() && $query->stmt->execute()) ? $query->stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        $output['list_reviews'] = [];

        $query = $this->modx->newQuery('SkarbReview');
        $query->select('
            SkarbReview.id, 
            SkarbReview.item_id, 
            SkarbReview.value, 
            SkarbReview.createdon, 
            SkarbReview.user_id, 
            SkarbReview.user_name, 
            modUser.username, 
            modUserProfile.internalKey, 
            modUserProfile.photo, 
            modUserProfile.fullname, 
            SkarbReviewPhoto.photo as anonim_photo,
            modResource.uri,
            modResource.pagetitle,
            VovCompanyPhoto.value as photo_company,
            VovCompanyAddress.value as address,
            SkarbLike.id as like_id,
            SkarbLike.votes_up');
        $query->innerJoin('modResource', 'modResource', 'modResource.id = SkarbReview.item_id AND modResource.template = 12 AND modResource.published = 1');
        $query->leftJoin('VovCompanyPhoto', 'VovCompanyPhoto', 'VovCompanyPhoto.id_company = SkarbReview.item_id AND VovCompanyPhoto.active = 1');
        $query->leftJoin('VovCompanyAddress', 'VovCompanyAddress', 'VovCompanyAddress.id_company = SkarbReview.item_id AND VovCompanyAddress.active = 1');    
        $query->leftJoin('SkarbReviewPhoto', 'SkarbReviewPhoto', 'SkarbReviewPhoto.id = SkarbReview.user_photo AND SkarbReview.user_photo > 0');
        $query->leftJoin('modUser', 'modUser', 'modUser.id = SkarbReview.user_id');
        $query->leftJoin('modUserProfile', 'modUserProfile', 'modUserProfile.internalKey = modUser.id');
        $query->leftJoin('SkarbLike', 'SkarbLike', 'SkarbLike.type = 2 AND SkarbLike.item_id = SkarbReview.id');
        $query->where([
            'SkarbReview.hidden' => 0,
            'SkarbReview.hidden_tape' => 0,
            'SkarbReview.deletedon' => null,
        ]);
        if (!empty($array['user_id'])) {
            if ($array['user_anonim']) {
                $query->where([
                    'SkarbReview.user_name' => $reviews_info[0]['user_name'],
                    'SkarbReview.user_key' => $reviews_info[0]['user_key'],
                ]);
            } else {
                $query->where([
                    'SkarbReview.user_id' => $array['user_id'],
                ]);
            }
        } else {
            if (!empty($array['id_city'])) {
                $query->where([
                    'modResource.id_city' => $array['id_city'],
                ]);
            }
        }
        $query->sortby('SkarbReview.id', 'DESC');
        $query->limit($array['limit'], $array['offset']);
        $results = ($query->prepare() && $query->stmt->execute()) ? $query->stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        
        $output['count'] = 0;
        foreach ($results as $val) {
            $arr = [];
            $arr['id'] = $val['id'];
            $arr['item_id'] = $val['item_id'];
            $arr['photo_company'] = $val['photo_company'];
            $arr['uri'] = $val['uri'];
            
            if ($val['user_id'] > 0) {
                $arr['user_photo'] = $val['photo'];
                $arr['user_name'] = (!empty($val['fullname'])) ? $val['fullname'] : $val['username'];
                $arr['user_id'] = $val['internalKey'];
            } else {
                $arr['user_photo'] = $this->avatar_dir . $val['anonim_photo'] . '_30x30.jpg';
                $arr['user_name'] = stripslashes($val['user_name']);
                $arr['user_id'] = '0' . strrev($val['id']);                
            }
            
            $arr['createdon_ago'] = $this->SkarbClear->showDateAgoSk($val['createdon'], false);

            $arr['pagetitle'] = htmlspecialchars_decode($val['pagetitle']);
            $arr['pagetitle'] = str_replace(array('\'', '"'), '', $arr['pagetitle']);
            if (mb_strlen($arr['pagetitle'], 'UTF-8') > 35) $arr['pagetitle'] = mb_substr($arr['pagetitle'], 0, 35, 'UTF-8') . '...';
            if (!empty($val['address'])) {
                $val['address'] = json_decode($val['address'], 1);
                $arr['address'] = (isset($val['address']['addr'])) ? $val['address']['addr'] : '';
            }
            
            $arr['likes_votes_up'] = $val['votes_up'];
            $arr['likes_voted'] = false;
            if (!empty($val['votes_up'])) {
                if ($val['votes_up'] > 10) $arr['good_like_sk'] = 'good_like_sk';
                $q = $this->modx->newQuery('SkarbLikeVotes');
                $q->select('SkarbLikeVotes.id');
                $q->where([
                   'SkarbLikeVotes.like_id' => $val['like_id'],
                ]);
                if (!$this->user_anonim) {
                    $q->where([
                       'SkarbLikeVotes.user_id' => $this->user_id,
                    ]);
                } else {
                    $q->where([
                       'SkarbLikeVotes.user_key' => $this->user_id,
                    ]);
                }
                $q->limit(1);
            
                if($this->modx->getValue($q->prepare())) $arr['likes_voted'] = true;
            }

            $arr['show_createdon'] = $array['show_createdon'];
            $file_text = $this->file_dir . $val['item_id'] . '/reviews/' . $val['id'] . '/text/' . $val['value'] . '.txt';    
            if (file_exists($file_text)) $arr['text'] = file_get_contents($file_text);
            
            $path_files = $this->file_dir . $val['item_id'] . '/reviews/' . $val['id'] . '/img/';
            if (file_exists($path_files)) {
                $it = new FilesystemIterator($path_files);
                foreach ($it as $fileinfo) {
                    if (is_file($fileinfo)) {
                        $idx = explode('-skarb-', $fileinfo->getFilename())[0];
                        $arr['files'][$idx]['filename'] = $fileinfo->getFilename();
                    }
                }
                if (!empty($arr['files'])) ksort($arr['files']);
            }
            $arr['path_files'] = str_replace(MODX_BASE_PATH, '', $path_files);
            
            if (isset($_COOKIE['skarbHiddenReviews']) && !empty($_COOKIE['skarbHiddenReviews'])) {
                $list_hidden_reviews = json_decode($_COOKIE['skarbHiddenReviews'], 1);
                if (is_array($list_hidden_reviews) && in_array($val['id'], $list_hidden_reviews)) $arr['hidden_review'] = true;
            }
            
            ++$output['count'];
            $output['list_reviews'][] = $arr;
        }
        
        if ($array['offset'] > 0) $output['only_result'] = true;
        $output['limit'] = $array['limit'];
        $output['id_city'] = $array['id_city'];
        $output['user_id'] = $array['user_id'];
        $output['user_anonim'] = $array['user_anonim'];
        $output['show_createdon'] = $array['show_createdon'];
        
        return $output;
    }






    //remove uploaded img
    public function deleteUploadedImageReview($array = []) 
    {
        if (!isset($array['item_id']) || $array['item_id'] != preg_replace('/[^0-9]/', '', $array['item_id'])) return false;
        
        if ($item_id = $this->SkarbClear->isExistsCompany(preg_replace('/[^0-9]/', '', $array['item_id']))) {        
            $array['filename'] = preg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $array['filename']);
            $array['filename'] = preg_replace("([\.]{2,})", '', $array['filename']);
            $tmp_dir = $this->tmp_dir . $item_id . '/img/';
            
            if (file_exists($tmp_dir . $array['filename'])) {
                $iterator = new RecursiveDirectoryIterator($tmp_dir);
                $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
                foreach ($files as $file) {
                    if (!is_dir($file) && $file->getFilename() == $array['filename']) unlink($file->getPathname());
                }
            }
        }
    }
    
    



    //report to review
    public function reportReviewSk($array = []) 
    {
        $array['review_id'] = preg_replace('/[^0-9]/', '', $array['review_id']);
    
        if (empty($array['review_id'])) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_default'), NULL);
        $array['cause'] = htmlspecialchars_decode($array['cause']);
        $array['cause'] = trim(strip_tags($array['cause']));
        $array['cause'] = preg_replace('|[\s]+|s', ' ', $array['cause']); 
        $array['cause'] = addslashes($array['cause']);
        if (empty($array['cause'])) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_empty_review'), null);
        
        if (!$reviewtObj = $this->modx->getObject('SkarbReview', ['id' => $array['review_id'], 'deletedon' => null])) {
            return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_review_remove'), null);
        }
        
        if ($reportObj = $this->modx->newObject('SkarbReviewReport')) {    
            $reportObj->set('review_id', $array['review_id']);
            if ($this->user_anonim) {
                $reportObj->set('user_key', $this->user_id);
            } else {
                $reportObj->set('user_id', $this->user_id);
            }
            $reportObj->set('cause', $array['cause']);
            $reportObj->set('createdon', time());
            $reportObj->save();
        }
        
        if (!$this->user_anonim && $this->user_id == $reviewtObj->get('user_id') || $this->user_anonim && $this->user_id == $reviewtObj->get('user_key') && (time() - $reviewtObj->get('createdon')) < 3600) {
            $reviewtObj->set('deletedon', time());
            $reviewtObj->save();
            $deleted = true;
        } elseif ($this->user_admin) {
            $reviewtObj->set('deletedon', time());
            $reviewtObj->set('deletedby', $this->user_id);
            $reviewtObj->save();
            $deleted = true;            
        } else {
            if (isset($_COOKIE['skarbHiddenReviews']) && !empty($_COOKIE['skarbHiddenReviews'])) {
                $list_hidden_reviews = json_decode($_COOKIE['skarbHiddenReviews'], 1);
            }
            $list_hidden_reviews[] = $array['review_id'];
            setcookie('skarbHiddenReviews', json_encode($list_hidden_reviews), time() + 21474836, '/');
        }
        
        if (isset($deleted)) {
            if ($reviewtObj->get('thread') > 0) {
                $sql = $this->modx->query('SELECT createdon FROM ' . $this->modx->getTableName('SkarbReview') . ' WHERE thread = ' . $reviewtObj->get('thread') . ' AND id != ' . $reviewtObj->get('id') . ' AND deletedon IS NULL AND hidden = 0 ORDER BY id DESC LIMIT 0, 1');
                $result = $sql->fetch(PDO::FETCH_ASSOC);
                if (isset($result['createdon'])) {
                   $this->modx->exec('UPDATE ' . $this->modx->getTableName('SkarbReview') . ' SET updatedon_thread = ' . $result['createdon'] . ' WHERE id = ' . $reviewtObj->get('thread'));
                } else {
                   $this->modx->exec('UPDATE ' . $this->modx->getTableName('SkarbReview') . ' SET updatedon_thread = createdon WHERE id = ' . $reviewtObj->get('thread'));
                }
            }
        }

        return $this->SkarbAnswer->ReturnAnswer(true, 'success_delete_comment', '', ['review_id' => $array['review_id']]);
    }
    






    //upload img to review
    public function uploadImageReview($array = []) 
    {
        if (!isset($array['item_id']) || $array['item_id'] != preg_replace('/[^0-9]/', '', $array['item_id'])) $array['item_id'] = $this->modx->resource->id;
        
        if ($item_id = $this->SkarbClear->isExistsCompany($array['item_id'])) {
            $tmp_dir = $this->tmp_dir . $item_id . '/img/';
            if (!file_exists($tmp_dir)) mkdir($tmp_dir, 0755, true); 
        
            $countFilesObj = new FilesystemIterator($tmp_dir, FilesystemIterator::SKIP_DOTS);
            $count_files = iterator_count($countFilesObj);
            if ($count_files > 10) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_limit_img'), NULL);

            $upload_json = $this->SkarbUpload->uploadImageSk($array, ['min_width' => 100, 'min_height' => 60]);
            $upload = json_decode($upload_json, 1);
            if (!$upload['success']) return $upload_json;
        
            if (file_exists($upload['message']['image_path'] . $upload['message']['image_name'])) {
                $array['idx'] = (!isset($array['idx'])) ? 0 : preg_replace('/[^0-9]/', '', $array['idx']);
                $new_image_name = $array['idx'] . '-skarb-' . rand(1111, 9999) . $upload['message']['image_name'];
                rename($upload['message']['image_path'] . $upload['message']['image_name'], $tmp_dir . $new_image_name);
               
               if (file_exists($tmp_dir . $new_image_name)) {
                    $arr_size_thumb = ['150' => '90'];
                    foreach ($arr_size_thumb as $width_thumb => $height_thumb) {
                        $dir_thumb_size = $tmp_dir . 'thumb/' . $width_thumb . 'x' . $height_thumb . '/';
                        if (!file_exists($dir_thumb_size . $new_image_name)) {
                            if (!file_exists($dir_thumb_size)) mkdir($dir_thumb_size, 0755, true);
                            $imagick = new Imagick($tmp_dir . $new_image_name);
                            $imagick->cropThumbnailImage($width_thumb, $height_thumb);
                            $imagick->writeImage($dir_thumb_size . $new_image_name);
                            $imagick->clear();
                        }
                    }
                }
            }
            
            return $this->SkarbAnswer->ReturnAnswer(true, 'success_upload_img_review', NULL, ['path' => str_replace(MODX_BASE_PATH, '', $tmp_dir), 'filename' => $new_image_name, 'item_id' => $item_id, 'idx' => $array['idx']]);
        }
    }
    





    //remove directory
    public function removeDirRecursiveSk($dir = '') 
    {
        if (!empty($dir) && file_exists($dir)) {
            $iterator = new RecursiveDirectoryIterator($dir);
            $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if (!is_dir($file)) {
                    unlink($file->getPathname());
                } else {
                    rmdir($file->getPathname());
                }
            }
            rmdir($dir);
        }
    }
    
    
    
    
    
    
    //get list img
    public function getListFilesReviewsSk($item_id = 0, $review_id = 0, $tmp = true) 
    {
        $list_files = [];
        $tmp_dir = ($tmp) ? $this->tmp_dir . $item_id . '/img/' : $this->file_dir . $item_id . '/reviews/' . $review_id . '/img/';
        if (file_exists($tmp_dir)) {
            $it = new FilesystemIterator($tmp_dir);
            foreach ($it as $fileinfo) {
                if (is_file($fileinfo)) {
                    $idx = explode('-skarb-', $fileinfo->getFilename())[0];
                    $list_files['files'][$idx]['filename'] = $fileinfo->getFilename();
                }
            }
            
            if (!empty($list_files['files'])) {
                ksort($list_files['files']);
                $list_files['path'] = str_replace(MODX_BASE_PATH, '', $tmp_dir);
            }
        }
        
        return $list_files;
    }






    //formatting text
    public function cleanTextReviewCompanySk($text = '') 
    {    
        $text = htmlspecialchars_decode($text);
        $text = trim(strip_tags($text, '<i><u><ol><ul><li>'));
        $text = preg_replace('/[\r\n]{2,}/ui', '<br><br>', $text);
        $text = preg_replace('|[\r\n]+|s', '<br>', $text);    
        $text = trim(strip_tags($text, '<br><div><i><u><ol><ul><li>'));
        $text = preg_replace('|[\s]+|s', ' ', $text); 
        $text = preg_replace('#(</?\w+)(?:\s(?:[^<>/]|/[^<>])*)?(/?>)#ui', '$1$2', $text);
        if (iconv_strlen($text) > 10000) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_review_long_text'), NULL);

        if (!empty($text)) {
            // censure review 
            if ($this->modx->loadClass('Text_Censure', $this->modx->getOption('core_path') . 'components/skarb/handlers/censuremaster/', true, true)) {
                $text = Text_Censure::parse($text, '1', '', false, ' *** ');
            }
            
            //replace link
            $regUrl = '~((https?://)?([-\w\@]+\.[-\w\.]+)+\w(:\d+)?(/([-\w/_\.]*(\?\S+)?)?)*)~';
            if (preg_match_all($regUrl, $text, $matches)) {
                $list_links = $matches[0];
                // function string_sort($a, $b)
                // {
                    // if ($a == $b) return 0;
                    // return (strlen($a) < strlen($b)) ? 1 : -1;
                // }
                // usort($list_links, 'string_sort');
                
                foreach ($list_links as $link) {
                    if (!strpos($link, '@')) {
                        $url = preg_replace("/(^(http(s)?:\/\/|www\.))?(www\.)?([a-z-\.0-9]+)/","$5", $link);
                        if (!preg_match('~^(?:f|ht)tps?://~i', $url)) $url = 'https://' . $url;
                        
                        $url = parse_url($url, PHP_URL_HOST);
                        if(stristr($url, $this->modx->getOption('site_name'))) continue;
                        
                        $url = implode('.', array_slice(explode('.', $url), -2));
                        if(preg_match("/^([a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,6})/", $url, $domain)) $url = $domain[1];
                        
                        $tlds = "/^[-a-z0-9]{1,63}\.(AAA|AARP|ABARTH|ABB|ABBOTT|ABBVIE|ABC|ABLE|ABOGADO|ABUDHABI|AC|ACADEMY|ACCENTURE|ACCOUNTANT|ACCOUNTANTS|ACO|ACTIVE|ACTOR|AD|ADAC|ADS|ADULT|AE|AEG|AERO|AETNA|AF|AFAMILYCOMPANY|AFL|AFRICA|AG|AGAKHAN|AGENCY|AI|AIG|AIGO|AIRBUS|AIRFORCE|AIRTEL|AKDN|AL|ALFAROMEO|ALIBABA|ALIPAY|ALLFINANZ|ALLSTATE|ALLY|ALSACE|ALSTOM|AM|AMERICANEXPRESS|AMERICANFAMILY|AMEX|AMFAM|AMICA|AMSTERDAM|ANALYTICS|ANDROID|ANQUAN|ANZ|AO|AOL|APARTMENTS|APP|APPLE|AQ|AQUARELLE|AR|ARAB|ARAMCO|ARCHI|ARMY|ARPA|ART|ARTE|AS|ASDA|ASIA|ASSOCIATES|AT|ATHLETA|ATTORNEY|AU|AUCTION|AUDI|AUDIBLE|AUDIO|AUSPOST|AUTHOR|AUTO|AUTOS|AVIANCA|AW|AWS|AX|AXA|AZ|AZURE|BA|BABY|BAIDU|BANAMEX|BANANAREPUBLIC|BAND|BANK|BAR|BARCELONA|BARCLAYCARD|BARCLAYS|BAREFOOT|BARGAINS|BASEBALL|BASKETBALL|BAUHAUS|BAYERN|BB|BBC|BBT|BBVA|BCG|BCN|BD|BE|BEATS|BEAUTY|BEER|BENTLEY|BERLIN|BEST|BESTBUY|BET|BF|BG|BH|BHARTI|BI|BIBLE|BID|BIKE|BING|BINGO|BIO|BIZ|BJ|BLACK|BLACKFRIDAY|BLANCO|BLOCKBUSTER|BLOG|BLOOMBERG|BLUE|BM|BMS|BMW|BN|BNL|BNPPARIBAS|BO|BOATS|BOEHRINGER|BOFA|BOM|BOND|BOO|BOOK|BOOKING|BOOTS|BOSCH|BOSTIK|BOSTON|BOT|BOUTIQUE|BOX|BR|BRADESCO|BRIDGESTONE|BROADWAY|BROKER|BROTHER|BRUSSELS|BS|BT|BUDAPEST|BUGATTI|BUILD|BUILDERS|BUSINESS|BUY|BUZZ|BV|BW|BY|BZ|BZH|CA|CAB|CAFE|CAL|CALL|CALVINKLEIN|CAM|CAMERA|CAMP|CANCERRESEARCH|CANON|CAPETOWN|CAPITAL|CAPITALONE|CAR|CARAVAN|CARDS|CARE|CAREER|CAREERS|CARS|CARTIER|CASA|CASE|CASEIH|CASH|CASINO|CAT|CATERING|CATHOLIC|CBA|CBN|CBRE|CBS|CC|CD|CEB|CENTER|CEO|CERN|CF|CFA|CFD|CG|CH|CHANEL|CHANNEL|CHASE|CHAT|CHEAP|CHINTAI|CHRISTMAS|CHROME|CHRYSLER|CHURCH|CI|CIPRIANI|CIRCLE|CISCO|CITADEL|CITI|CITIC|CITY|CITYEATS|CK|CL|CLAIMS|CLEANING|CLICK|CLINIC|CLINIQUE|CLOTHING|CLOUD|CLUB|CLUBMED|CM|CN|CO|COACH|CODES|COFFEE|COLLEGE|COLOGNE|COM|COMCAST|COMMBANK|COMMUNITY|COMPANY|COMPARE|COMPUTER|COMSEC|CONDOS|CONSTRUCTION|CONSULTING|CONTACT|CONTRACTORS|COOKING|COOKINGCHANNEL|COOL|COOP|CORSICA|COUNTRY|COUPON|COUPONS|COURSES|CR|CREDIT|CREDITCARD|CREDITUNION|CRICKET|CROWN|CRS|CRUISE|CRUISES|CSC|CU|CUISINELLA|CV|CW|CX|CY|CYMRU|CYOU|CZ|DABUR|DAD|DANCE|DATA|DATE|DATING|DATSUN|DAY|DCLK|DDS|DE|DEAL|DEALER|DEALS|DEGREE|DELIVERY|DELL|DELOITTE|DELTA|DEMOCRAT|DENTAL|DENTIST|DESI|DESIGN|DEV|DHL|DIAMONDS|DIET|DIGITAL|DIRECT|DIRECTORY|DISCOUNT|DISCOVER|DISH|DIY|DJ|DK|DM|DNP|DO|DOCS|DOCTOR|DODGE|DOG|DOHA|DOMAINS|DOT|DOWNLOAD|DRIVE|DTV|DUBAI|DUCK|DUNLOP|DUNS|DUPONT|DURBAN|DVAG|DVR|DZ|EARTH|EAT|EC|ECO|EDEKA|EDU|EDUCATION|EE|EG|EMAIL|EMERCK|ENERGY|ENGINEER|ENGINEERING|ENTERPRISES|EPOST|EPSON|EQUIPMENT|ER|ERICSSON|ERNI|ES|ESQ|ESTATE|ESURANCE|ET|ETISALAT|EU|EUROVISION|EUS|EVENTS|EVERBANK|EXCHANGE|EXPERT|EXPOSED|EXPRESS|EXTRASPACE|FAGE|FAIL|FAIRWINDS|FAITH|FAMILY|FAN|FANS|FARM|FARMERS|FASHION|FAST|FEDEX|FEEDBACK|FERRARI|FERRERO|FI|FIAT|FIDELITY|FIDO|FILM|FINAL|FINANCE|FINANCIAL|FIRE|FIRESTONE|FIRMDALE|FISH|FISHING|FIT|FITNESS|FJ|FK|FLICKR|FLIGHTS|FLIR|FLORIST|FLOWERS|FLY|FM|FO|FOO|FOOD|FOODNETWORK|FOOTBALL|FORD|FOREX|FORSALE|FORUM|FOUNDATION|FOX|FR|FREE|FRESENIUS|FRL|FROGANS|FRONTDOOR|FRONTIER|FTR|FUJITSU|FUJIXEROX|FUN|FUND|FURNITURE|FUTBOL|FYI|GA|GAL|GALLERY|GALLO|GALLUP|GAME|GAMES|GAP|GARDEN|GB|GBIZ|GD|GDN|GE|GEA|GENT|GENTING|GEORGE|GF|GG|GGEE|GH|GI|GIFT|GIFTS|GIVES|GIVING|GL|GLADE|GLASS|GLE|GLOBAL|GLOBO|GM|GMAIL|GMBH|GMO|GMX|GN|GODADDY|GOLD|GOLDPOINT|GOLF|GOO|GOODHANDS|GOODYEAR|GOOG|GOOGLE|GOP|GOT|GOV|GP|GQ|GR|GRAINGER|GRAPHICS|GRATIS|GREEN|GRIPE|GROCERY|GROUP|GS|GT|GU|GUARDIAN|GUCCI|GUGE|GUIDE|GUITARS|GURU|GW|GY|HAIR|HAMBURG|HANGOUT|HAUS|HBO|HDFC|HDFCBANK|HEALTH|HEALTHCARE|HELP|HELSINKI|HERE|HERMES|HGTV|HIPHOP|HISAMITSU|HITACHI|HIV|HK|HKT|HM|HN|HOCKEY|HOLDINGS|HOLIDAY|HOMEDEPOT|HOMEGOODS|HOMES|HOMESENSE|HONDA|HONEYWELL|HORSE|HOSPITAL|HOST|HOSTING|HOT|HOTELES|HOTELS|HOTMAIL|HOUSE|HOW|HR|HSBC|HT|HU|HUGHES|HYATT|HYUNDAI|IBM|ICBC|ICE|ICU|ID|IE|IEEE|IFM|IKANO|IL|IM|IMAMAT|IMDB|IMMO|IMMOBILIEN|IN|INDUSTRIES|INFINITI|INFO|ING|INK|INSTITUTE|INSURANCE|INSURE|INT|INTEL|INTERNATIONAL|INTUIT|INVESTMENTS|IO|IPIRANGA|IQ|IR|IRISH|IS|ISELECT|ISMAILI|IST|ISTANBUL|IT|ITAU|ITV|IVECO|IWC|JAGUAR|JAVA|JCB|JCP|JE|JEEP|JETZT|JEWELRY|JIO|JLC|JLL|JM|JMP|JNJ|JO|JOBS|JOBURG|JOT|JOY|JP|JPMORGAN|JPRS|JUEGOS|JUNIPER|KAUFEN|KDDI|KE|KERRYHOTELS|KERRYLOGISTICS|KERRYPROPERTIES|KFH|KG|KH|KI|KIA|KIM|KINDER|KINDLE|KITCHEN|KIWI|KM|KN|KOELN|KOMATSU|KOSHER|KP|KPMG|KPN|KR|KRD|KRED|KUOKGROUP|KW|KY|KYOTO|KZ|LA|LACAIXA|LADBROKES|LAMBORGHINI|LAMER|LANCASTER|LANCIA|LANCOME|LAND|LANDROVER|LANXESS|LASALLE|LAT|LATINO|LATROBE|LAW|LAWYER|LB|LC|LDS|LEASE|LECLERC|LEFRAK|LEGAL|LEGO|LEXUS|LGBT|LI|LIAISON|LIDL|LIFE|LIFEINSURANCE|LIFESTYLE|LIGHTING|LIKE|LILLY|LIMITED|LIMO|LINCOLN|LINDE|LINK|LIPSY|LIVE|LIVING|LIXIL|LK|LOAN|LOANS|LOCKER|LOCUS|LOFT|LOL|LONDON|LOTTE|LOTTO|LOVE|LPL|LPLFINANCIAL|LR|LS|LT|LTD|LTDA|LU|LUNDBECK|LUPIN|LUXE|LUXURY|LV|LY|MA|MACYS|MADRID|MAIF|MAISON|MAKEUP|MAN|MANAGEMENT|MANGO|MAP|MARKET|MARKETING|MARKETS|MARRIOTT|MARSHALLS|MASERATI|MATTEL|MBA|MC|MCKINSEY|MD|ME|MED|MEDIA|MEET|MELBOURNE|MEME|MEMORIAL|MEN|MENU|MEO|MERCKMSD|METLIFE|MG|MH|MIAMI|MICROSOFT|MIL|MINI|MINT|MIT|MITSUBISHI|MK|ML|MLB|MLS|MM|MMA|MN|MO|MOBI|MOBILE|MOBILY|MODA|MOE|MOI|MOM|MONASH|MONEY|MONSTER|MOPAR|MORMON|MORTGAGE|MOSCOW|MOTO|MOTORCYCLES|MOV|MOVIE|MOVISTAR|MP|MQ|MR|MS|MSD|MT|MTN|MTR|MU|MUSEUM|MUTUAL|MV|MW|MX|MY|MZ|NA|NAB|NADEX|NAGOYA|NAME|NATIONWIDE|NATURA|NAVY|NBA|NC|NE|NEC|NET|NETBANK|NETFLIX|NETWORK|NEUSTAR|NEW|NEWHOLLAND|NEWS|NEXT|NEXTDIRECT|NEXUS|NF|NFL|NG|NGO|NHK|NI|NICO|NIKE|NIKON|NINJA|NISSAN|NISSAY|NL|NO|NOKIA|NORTHWESTERNMUTUAL|NORTON|NOW|NOWRUZ|NOWTV|NP|NR|NRA|NRW|NTT|NU|NYC|NZ|OBI|OBSERVER|OFF|OFFICE|OKINAWA|OLAYAN|OLAYANGROUP|OLDNAVY|OLLO|OM|OMEGA|ONE|ONG|ONL|ONLINE|ONYOURSIDE|OOO|OPEN|ORACLE|ORANGE|ORG|ORGANIC|ORIGINS|OSAKA|OTSUKA|OTT|OVH|PA|PAGE|PANASONIC|PANERAI|PARIS|PARS|PARTNERS|PARTS|PARTY|PASSAGENS|PAY|PCCW|PE|PET|PF|PFIZER|PG|PH|PHARMACY|PHD|PHILIPS|PHONE|PHOTO|PHOTOGRAPHY|PHOTOS|PHYSIO|PIAGET|PICS|PICTET|PICTURES|PID|PIN|PING|PINK|PIONEER|PIZZA|PK|PL|PLACE|PLAY|PLAYSTATION|PLUMBING|PLUS|PM|PN|PNC|POHL|POKER|POLITIE|PORN|POST|PR|PRAMERICA|PRAXI|PRESS|PRIME|PRO|PROD|PRODUCTIONS|PROF|PROGRESSIVE|PROMO|PROPERTIES|PROPERTY|PROTECTION|PRU|PRUDENTIAL|PS|PT|PUB|PW|PWC|PY|QA|QPON|QUEBEC|QUEST|QVC|RACING|RADIO|RAID|RE|READ|REALESTATE|REALTOR|REALTY|RECIPES|RED|REDSTONE|REDUMBRELLA|REHAB|REISE|REISEN|REIT|RELIANCE|REN|RENT|RENTALS|REPAIR|REPORT|REPUBLICAN|REST|RESTAURANT|REVIEW|REVIEWS|REXROTH|RICH|RICHARDLI|RICOH|RIGHTATHOME|RIL|RIO|RIP|RMIT|RO|ROCHER|ROCKS|RODEO|ROGERS|ROOM|RS|RSVP|RU|RUGBY|RUHR|RUN|RW|RWE|RYUKYU|SA|SAARLAND|SAFE|SAFETY|SAKURA|SALE|SALON|SAMSCLUB|SAMSUNG|SANDVIK|SANDVIKCOROMANT|SANOFI|SAP|SAPO|SARL|SAS|SAVE|SAXO|SB|SBI|SBS|SC|SCA|SCB|SCHAEFFLER|SCHMIDT|SCHOLARSHIPS|SCHOOL|SCHULE|SCHWARZ|SCIENCE|SCJOHNSON|SCOR|SCOT|SD|SE|SEARCH|SEAT|SECURE|SECURITY|SEEK|SELECT|SENER|SERVICES|SES|SEVEN|SEW|SEX|SEXY|SFR|SG|SH|SHANGRILA|SHARP|SHAW|SHELL|SHIA|SHIKSHA|SHOES|SHOP|SHOPPING|SHOUJI|SHOW|SHOWTIME|SHRIRAM|SI|SILK|SINA|SINGLES|SITE|SJ|SK|SKI|SKIN|SKY|SKYPE|SL|SLING|SM|SMART|SMILE|SN|SNCF|SO|SOCCER|SOCIAL|SOFTBANK|SOFTWARE|SOHU|SOLAR|SOLUTIONS|SONG|SONY|SOY|SPACE|SPIEGEL|SPORT|SPOT|SPREADBETTING|SR|SRL|SRT|ST|STADA|STAPLES|STAR|STARHUB|STATEBANK|STATEFARM|STATOIL|STC|STCGROUP|STOCKHOLM|STORAGE|STORE|STREAM|STUDIO|STUDY|STYLE|SU|SUCKS|SUPPLIES|SUPPLY|SUPPORT|SURF|SURGERY|SUZUKI|SV|SWATCH|SWIFTCOVER|SWISS|SX|SY|SYDNEY|SYMANTEC|SYSTEMS|SZ|TAB|TAIPEI|TALK|TAOBAO|TARGET|TATAMOTORS|TATAR|TATTOO|TAX|TAXI|TC|TCI|TD|TDK|TEAM|TECH|TECHNOLOGY|TEL|TELECITY|TELEFONICA|TEMASEK|TENNIS|TEVA|TF|TG|TH|THD|THEATER|THEATRE|TIAA|TICKETS|TIENDA|TIFFANY|TIPS|TIRES|TIROL|TJ|TJMAXX|TJX|TK|TKMAXX|TL|TM|TMALL|TN|TO|TODAY|TOKYO|TOOLS|TOP|TORAY|TOSHIBA|TOTAL|TOURS|TOWN|TOYOTA|TOYS|TR|TRADE|TRADING|TRAINING|TRAVEL|TRAVELCHANNEL|TRAVELERS|TRAVELERSINSURANCE|TRUST|TRV|TT|TUBE|TUI|TUNES|TUSHU|TV|TVS|TW|TZ|UA|UBANK|UBS|UCONNECT|UG|UK|UNICOM|UNIVERSITY|UNO|UOL|UPS|US|UY|UZ|VA|VACATIONS|VANA|VANGUARD|VC|VE|VEGAS|VENTURES|VERISIGN|VERSICHERUNG|VET|VG|VI|VIAJES|VIDEO|VIG|VIKING|VILLAS|VIN|VIP|VIRGIN|VISA|VISION|VISTA|VISTAPRINT|VIVA|VIVO|VLAANDEREN|VN|VODKA|VOLKSWAGEN|VOLVO|VOTE|VOTING|VOTO|VOYAGE|VU|VUELOS|WALES|WALMART|WALTER|WANG|WANGGOU|WARMAN|WATCH|WATCHES|WEATHER|WEATHERCHANNEL|WEBCAM|WEBER|WEBSITE|WED|WEDDING|WEIBO|WEIR|WF|WHOSWHO|WIEN|WIKI|WILLIAMHILL|WIN|WINDOWS|WINE|WINNERS|WME|WOLTERSKLUWER|WOODSIDE|WORK|WORKS|WORLD|WOW|WS|WTC|WTF|XBOX|XEROX|XFINITY|XIHUAN|XIN|XPERIA|XXX|XYZ|YACHTS|YAHOO|YAMAXUN|YANDEX|YE|YODOBASHI|YOGA|YOKOHAMA|YOU|YOUTUBE|YT|YUN|ZA|ZAPPOS|ZARA|ZERO|ZIP|ZIPPO|ZM|ZONE|ZUERICH|ZW)$/i";
                        if (preg_match($tlds, $url)) {
                            $short_link = $this->SkarbShortLink->SetShortLinks(json_encode([0 => ['user_key' => $_SESSION['skarbSiteUser'], 'uid' => $this->modx->user->id, 'full_link' => $link]], JSON_UNESCAPED_UNICODE), true);
                            $short_link = json_decode($short_link, 1);
                            if (!empty($short_link[0]['short_link'])) {
                                $text = str_replace($link, ' <a href="/link/' . $short_link[0]['short_link'] . '" target="_blank" class="skarb_short_review_link">' . $link .'</a> ', $text);
                            }
                        }
                    }
                }
            }
        }
        
        return $this->SkarbAnswer->ReturnAnswer(true, 'success', $text, NULL);
    }






    //formatting username
    public function cleanNameReviewCompanySk($name = '', $any_username = false) 
    {    
        $name = str_replace(['[',']','"'], '', json_encode($name, JSON_UNESCAPED_UNICODE));
        $name = htmlspecialchars_decode($name);
        $name = trim(strip_tags($name));
        $name = preg_replace('|[\s]+|s', ' ', $name); 
        $name = addslashes($name);
        if (empty($name)) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_review_empty_name'), NULL);            
        
        if (!$any_username) {
            $sql = $this->modx->query('SELECT id FROM ' . $this->modx->getTableName('modUserProfile') . ' WHERE fullname LIKE \'' . $name . '\'');
            if ($sql->fetch(PDO::FETCH_ASSOC)) return $this->SkarbAnswer->ReturnAnswer(false, 'error', $this->modx->lexicon('sk_error_review_disable_name'), NULL);
            setcookie('skarbAnonimUserName', $name, time() + 21474836, '/');
        }
        
        $user_photo = 0;
        $sql = $this->modx->query('SELECT user_photo, photo FROM ' . $this->modx->getTableName('SkarbReview') . ' LEFT JOIN ' . $this->modx->getTableName('SkarbReviewPhoto') . ' ON ' . $this->modx->getTableName('SkarbReviewPhoto') . '.id = ' . $this->modx->getTableName('SkarbReview') . '.user_photo WHERE user_key = \'' . $_SESSION['skarbSiteUser'] . '\' AND user_name = \'' . addslashes($name) . '\'');
        if ($result = $sql->fetch(PDO::FETCH_ASSOC)) {
            $user_photo = $result['user_photo'];
            $url_user_photo = $result['photo'];            
        } else {
            $sql = $this->modx->query('SELECT id, photo FROM ' . $this->modx->getTableName('SkarbReviewPhoto') . ' ORDER BY rand()');
            if ($result = $sql->fetch(PDO::FETCH_ASSOC)) {
                $user_photo = $result['id'];
                $url_user_photo = $result['photo'];
            }
        }
        
        return $this->SkarbAnswer->ReturnAnswer(true, 'success', '', ['user_name' => $name, 'user_photo' => $user_photo, 'url_user_photo' => $url_user_photo]);
    }
    
    
    
    
    

    //get random name
    public function getRandomUsernameSk() 
    {
        $sql = $this->modx->query('SELECT username FROM ' . $this->modx->getTableName('SkarbListUsername') . ' ORDER BY rand() LIMIT 0, 1');
        return ($result = $sql->fetch(PDO::FETCH_ASSOC)) ? trim($result['username']) : 'Anonim';    
    }

    
}
