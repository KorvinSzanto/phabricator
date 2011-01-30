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

class DifferentialDiff extends DifferentialDAO {

  protected $revisionID;
  protected $authorPHID;

  protected $sourceMachine;
  protected $sourcePath;

  protected $sourceControlSystem;
  protected $sourceControlBaseRevision;
  protected $sourceControlPath;

  protected $lintStatus;
  protected $unitStatus;

  protected $lineCount;

  protected $branch;

  protected $parentRevisionID;
  protected $arcanistProject;
  protected $creationMethod;

  private $unsavedChangesets = array();
  private $changesets;

  public function addUnsavedChangeset(DifferentialChangeset $changeset) {
    if ($this->changesets === null) {
      $this->changesets = array();
    }
    $this->unsavedChangesets[] = $changeset;
    $this->changesets[] = $changeset;
    return $this;
  }

  public function attachChangesets(array $changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function getChangesets() {
    if ($this->changesets === null) {
      throw new Exception("Must load and attach changesets first!");
    }
    return $this->changesets;
  }

  public function loadChangesets() {
    if (!$this->getID()) {
      return array();
    }
    return id(new DifferentialChangeset())->loadAllWhere(
      'diffID = %d',
      $this->getID());
  }

  public function save() {
// TODO: sort out transactions
//    $this->openTransaction();
      $ret = parent::save();
      foreach ($this->unsavedChangesets as $changeset) {
        $changeset->setDiffID($this->getID());
        $changeset->save();
      }
//    $this->saveTransaction();
    return $ret;
  }

  public function delete() {
//    $this->openTransaction();
      foreach ($this->loadChangesets() as $changeset) {
        $changeset->delete();
      }
      $ret = parent::delete();
//    $this->saveTransaction();
    return $ret;
  }

  public static function newFromRawChanges(array $changes) {
    $diff = new DifferentialDiff();

    $lines = 0;
    foreach ($changes as $change) {
      $changeset = new DifferentialChangeset();
      $add_lines = 0;
      $del_lines = 0;
      foreach ($change->getHunks() as $hunk) {
        $dhunk = new DifferentialHunk();
        $dhunk->setOldOffset($hunk->getOldOffset());
        $dhunk->setOldLen($hunk->getOldLength());
        $dhunk->setNewOffset($hunk->getNewOffset());
        $dhunk->setNewLen($hunk->getNewLength());
        $dhunk->setChanges($hunk->getCorpus());
        $changeset->addUnsavedHunk($dhunk);
        $add_lines += $hunk->getAddLines();
        $del_lines += $hunk->getDelLines();
        $lines += $add_lines + $del_lines;
      }

      $changeset->setOldFile($change->getOldPath());
      $changeset->setFilename($change->getCurrentPath());
      $changeset->setChangeType($change->getType());

      $changeset->setFileType($change->getFileType());
      $changeset->setMetadata($change->getAllMetadata());
      $changeset->setOldProperties($change->getOldProperties());
      $changeset->setNewProperties($change->getNewProperties());
      $changeset->setAwayPaths($change->getAwayPaths());
      $changeset->setAddLines($add_lines);
      $changeset->setDelLines($del_lines);

      $diff->addUnsavedChangeset($changeset);
    }
    $diff->setLineCount($lines);

    return $diff;
  }

}
