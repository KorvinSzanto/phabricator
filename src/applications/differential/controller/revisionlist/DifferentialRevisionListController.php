<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class DifferentialRevisionListController extends DifferentialController {

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {

    $filters = array(
      'active' => array(
        'name'  => 'Active Revisions',
        'queries' => array(
          array(
            'query'
              => DifferentialRevisionListData::QUERY_NEED_ACTION_FROM_SELF,
            'header' => 'Action Required',
            'nodata' => 'You have no revisions requiring action.',
          ),
          array(
            'query'
              => DifferentialRevisionListData::QUERY_NEED_ACTION_FROM_OTHERS,
            'header' => 'Waiting on Others',
            'nodata' => 'You have no revisions waiting on others',
          ),
        ),
      ),
      'open' => array(
        'name' => 'Open Revisions',
        'queries' => array(
          array(
            'query' => DifferentialRevisionListData::QUERY_OPEN_OWNED,
            'header' => 'Open Revisions',
          ),
        ),
      ),
      'reviews' => array(
        'name' => 'Open Reviews',
        'queries' => array(
          array(
            'query' => DifferentialRevisionListData::QUERY_OPEN_REVIEWER,
            'header' => 'Open Reviews',
          ),
        ),
      ),
      'all' => array(
        'name' => 'All Revisions',
        'queries' => array(
          array(
            'query' => DifferentialRevisionListData::QUERY_OWNED,
            'header' => 'All Revisions',
          ),
        ),
      ),
      'related' => array(
        'name' => 'All Revisions and Reviews',
        'queries' => array(
          array(
            'query' => DifferentialRevisionListData::QUERY_OWNED_OR_REVIEWER,
            'header' => 'All Revisions and Reviews',
          ),
        ),
      ),
    );

    if (empty($filters[$this->filter])) {
      $this->filter = key($filters);
    }

    $request = $this->getRequest();
    $user = $request->getUser();

    $queries = array();
    $filter = $filters[$this->filter];
    foreach ($filter['queries'] as $query) {
      $query_object = new DifferentialRevisionListData(
        $query['query'],
        array($user->getPHID()));
      $queries[] = array(
        'object' => $query_object,
      ) + $query;
    }

    $side_nav = new AphrontSideNavView();
    foreach ($filters as $filter_name => $filter_desc) {
      $selected = ($filter_name == $this->filter);
      $side_nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/differential/filter/'.$filter_name.'/',
            'class' => $selected ? 'aphront-side-nav-selected' : null,
          ),
          phutil_escape_html($filter_desc['name'])));
    }

    foreach ($queries as $query) {
      $table = $this->renderRevisionTable(
        $query['object']->loadRevisions(),
        $query['header'],
        idx($query, 'nodata'));
      $side_nav->appendChild($table);
    }

    return $this->buildStandardPageResponse(
      $side_nav,
      array(
        'title' => 'Differential Home',
      ));
  }

  private function renderRevisionTable(array $revisions, $header, $nodata) {

    $rows = array();
    foreach ($revisions as $revision) {
      $status = DifferentialRevisionStatus::getNameForRevisionStatus(
        $revision->getStatus());

      $rows[] = array(
        'D'.$revision->getID(),
        phutil_render_tag(
          'a',
          array(
            'href' => '/D'.$revision->getID(),
          ),
          phutil_escape_html($revision->getTitle())),
        phutil_escape_html($status),
        number_format($revision->getLineCount()),
        $revision->getAuthorPHID(),
        'TODO',
        $revision->getDateModified(),
        $revision->getDateCreated(),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Revision',
        'Status',
        'Lines',
        'Author',
        'Reviewers',
        'Updated',
        'Created',
      ));
    $table->setColumnClasses(
      array(
        null,
        'wide',
        null,
        null,
        null,
        null,
        null,
        null,
      ));
    if ($nodata !== null) {
      $table->setNoDataString($nodata);
    }


    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->appendChild($table);

    return $panel;
  }

}
